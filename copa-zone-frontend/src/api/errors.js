export const DEFAULT_REQUEST_ERROR_MESSAGE = 'Não foi possível concluir a operação.';

export const DEFAULT_API_ERROR_MESSAGE_MAP = Object.freeze({
  'auth.failed': 'E-mail ou senha incorretos.',
  'Unauthenticated.': 'Sua sessão expirou. Faça login novamente.',
  'The given data was invalid.': 'Revise os campos informados e tente novamente.',
  'passwords.throttled': 'Aguarde alguns instantes antes de tentar novamente.',
  'passwords.token': 'O link para redefinir a senha é inválido ou expirou.',
  'passwords.user': 'Não encontramos um usuário com esse e-mail.',
});

const TRANSLATION_KEY_PATTERN = /^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/i;

function normalizeCandidateMessage(value, messageMap = DEFAULT_API_ERROR_MESSAGE_MAP) {
  if (typeof value !== 'string') {
    return '';
  }

  const normalized = value.trim();

  if (!normalized) {
    return '';
  }

  if (Object.prototype.hasOwnProperty.call(messageMap, normalized)) {
    return messageMap[normalized];
  }

  if (TRANSLATION_KEY_PATTERN.test(normalized)) {
    return '';
  }

  return normalized;
}

function getFirstServerErrorMessage(errors) {
  if (!errors || typeof errors !== 'object') {
    return '';
  }

  for (const value of Object.values(errors)) {
    if (Array.isArray(value)) {
      const firstMessage = value.find((entry) => typeof entry === 'string' && entry.trim());

      if (firstMessage) {
        return firstMessage.trim();
      }
    }

    if (typeof value === 'string' && value.trim()) {
      return value.trim();
    }
  }

  return '';
}

export function getRequestErrorMessage(
  error,
  { fallbackMessage = DEFAULT_REQUEST_ERROR_MESSAGE, messageMap = DEFAULT_API_ERROR_MESSAGE_MAP } = {},
) {
  const candidates = [
    getFirstServerErrorMessage(error?.payload?.errors),
    error?.payload?.message,
    error?.payload?.error?.message,
    error?.message,
  ];

  for (const candidate of candidates) {
    const message = normalizeCandidateMessage(candidate, messageMap);

    if (message) {
      return message;
    }
  }

  return fallbackMessage;
}

export function applyServerFieldErrors(
  serverErrors,
  setError,
  allowedFields = [],
  { messageMap = DEFAULT_API_ERROR_MESSAGE_MAP } = {},
) {
  const allowAllFields = !Array.isArray(allowedFields) || allowedFields.length === 0;

  Object.entries(serverErrors ?? {}).forEach(([field, messages]) => {
    if (!allowAllFields && !allowedFields.includes(field)) {
      return;
    }

    const firstMessage = Array.isArray(messages)
      ? messages.find((entry) => typeof entry === 'string' && entry.trim())
      : messages;

    const normalizedMessage = normalizeCandidateMessage(firstMessage, messageMap);

    if (!normalizedMessage) {
      return;
    }

    setError(field, {
      type: 'server',
      message: normalizedMessage,
    });
  });
}
