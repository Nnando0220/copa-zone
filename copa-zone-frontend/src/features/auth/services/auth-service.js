import { apiRequest, refreshCsrfToken } from '../../../api/client';

export const authService = {
  async me() {
    return apiRequest('/me');
  },

  async login(payload) {
    await refreshCsrfToken();

    return apiRequest('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email: payload.email,
        password: payload.password,
        remember: Boolean(payload.remember),
      }),
    });
  },

  async register(payload) {
    await refreshCsrfToken();

    return apiRequest('/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  async logout() {
    return apiRequest('/auth/logout', {
      method: 'POST',
    });
  },
};
