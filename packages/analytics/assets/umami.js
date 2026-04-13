/**
 * Umami custom event helpers — shared across Waaseyaa apps.
 *
 * Usage: import { trackSearch } from '/js/umami.js';
 *
 * All functions are no-ops when Umami is not loaded (local / staging without tracker).
 */

function track(event, data = {}) {
  if (typeof window.umami?.track !== 'function') return;
  window.umami.track(event, data);
}

export function trackSearch(query, resultCount) {
  track('search', { query, result_count: resultCount });
}

export function trackTeachingView(teachingId, title) {
  track('teaching_view', { id: teachingId, title });
}

export function trackGamePlay(gameType, action) {
  track('game_play', { game_type: gameType, action });
}

export function trackVolunteerAction(action) {
  track('volunteer_action', { action });
}
