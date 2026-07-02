import { apiRequest } from '../../../api/client';

function withQuery(path, params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '' && value !== 'all') {
      query.set(key, value);
    }
  });

  const serialized = query.toString();

  return serialized ? `${path}?${serialized}` : path;
}

export const portalService = {
  dashboard() {
    return apiRequest('/dashboard');
  },

  myLeagues() {
    return apiRequest('/leagues');
  },

  publicLeagues() {
    return apiRequest('/leagues/public');
  },

  worldCup() {
    return apiRequest('/world-cup');
  },

  worldCupMatches(params = {}) {
    return apiRequest(withQuery('/world-cup/matches', params));
  },

  worldCupGroups() {
    return apiRequest('/world-cup/groups');
  },

  worldCupBracket() {
    return apiRequest('/world-cup/bracket');
  },

  worldCupSyncStatus() {
    return apiRequest('/world-cup/sync-status');
  },

  leagueMatches(leagueId, params = {}) {
    return apiRequest(withQuery(`/leagues/${leagueId}/world-cup/matches`, params));
  },

  leagueWorldCup(leagueId) {
    return apiRequest(`/leagues/${leagueId}/world-cup`);
  },

  leagueGroups(leagueId) {
    return apiRequest(`/leagues/${leagueId}/world-cup/groups`);
  },

  leagueStages(leagueId) {
    return apiRequest(`/leagues/${leagueId}/world-cup/stages`);
  },

  leagueBracket(leagueId) {
    return apiRequest(`/leagues/${leagueId}/world-cup/bracket`);
  },

  leagueDetails(leagueId) {
    return apiRequest(`/leagues/${leagueId}`);
  },

  leaguePredictions(leagueId) {
    return apiRequest(`/leagues/${leagueId}/predictions`);
  },

  savePrediction(leagueId, matchId, payload) {
    return apiRequest(`/leagues/${leagueId}/matches/${matchId}/prediction`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  cancelPrediction(leagueId, predictionId) {
    return apiRequest(`/leagues/${leagueId}/predictions/${predictionId}`, {
      method: 'DELETE',
    });
  },

  leagueRanking(leagueId) {
    return apiRequest(`/leagues/${leagueId}/ranking`);
  },

  createLeague(payload) {
    return apiRequest('/leagues', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  joinPublicLeague(leagueId) {
    return apiRequest(`/leagues/${leagueId}/join`, {
      method: 'POST',
    });
  },

  leaveLeague(leagueId) {
    return apiRequest(`/leagues/${leagueId}/membership`, {
      method: 'DELETE',
    });
  },

  previewByCode(inviteCode) {
    return apiRequest('/leagues/invites/preview', {
      method: 'POST',
      body: JSON.stringify({ invite_code: inviteCode }),
    });
  },

  joinByCode(inviteCode) {
    return apiRequest('/leagues/join-by-code', {
      method: 'POST',
      body: JSON.stringify({ invite_code: inviteCode }),
    });
  },
};
