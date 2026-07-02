const DISPLAY_TIMEZONE = 'America/Sao_Paulo';

export function formatBrazilDateTime(value, fallback = 'Horario indefinido') {
  if (!value) {
    return fallback;
  }

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: DISPLAY_TIMEZONE,
  }).format(new Date(value));
}
