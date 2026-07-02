import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { API_BASE_URL, getXsrfToken, refreshCsrfToken } from '../api/client';

let echoInstance = null;

function broadcastAuthEndpoint() {
  return `${new URL(API_BASE_URL).origin}/broadcasting/auth`;
}

function makeChannelAuthorizer(channel) {
  return {
    authorize: async (socketId, callback) => {
      try {
        if (!getXsrfToken()) {
          await refreshCsrfToken();
        }

        const response = await fetch(broadcastAuthEndpoint(), {
          method: 'POST',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
          callback(true, payload);
          return;
        }

        callback(false, payload);
      } catch (error) {
        callback(true, error);
      }
    },
  };
}

export function getEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;

  if (!key) {
    return null;
  }

  if (echoInstance) {
    return echoInstance;
  }

  window.Pusher = Pusher;

  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
  const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: makeChannelAuthorizer,
  });

  return echoInstance;
}

export function leaveChannel(channel) {
  if (echoInstance && channel) {
    echoInstance.leave(channel);
  }
}
