// Per-site configuration.
//
// Every value here comes from a REACT_APP_* variable baked in at build time by
// scripts/build-site.sh, which reads sites/<name>.env. The defaults below are
// Alex's site, so a plain `npm start` or `npm run build` still works.
//
// If you add a site, add sites/<name>.env — don't add branches here.

const trimSlash = (url) => (url || '').replace(/\/+$/, '');

// If the build variable is missing, fall back to the domain the page is being
// served from. A misconfigured build then talks to its own server rather than
// the other site's — the failure is visible instead of silently cross-wired.
// Only on localhost, where there is no PHP, does it reach for a real server.
const servedFrom = typeof window !== 'undefined' ? window.location.origin : '';
const isLocalDev = /localhost|127\.0\.0\.1/.test(servedFrom);

export const API_BASE = trimSlash(
  process.env.REACT_APP_API_BASE ||
    (isLocalDev ? 'https://alexhixson.zerofour.tech' : servedFrom)
);

export const SITE_NAME = process.env.REACT_APP_SITE_NAME || 'ALEXHIXSON.COM';
export const SITE_NAME_SHORT = process.env.REACT_APP_SITE_NAME_SHORT || 'ALEX HIXSON';
export const INSTAGRAM_URL =
  process.env.REACT_APP_INSTAGRAM_URL || 'https://www.instagram.com/alex.hixson/';

// api('retrieve_messages.php') -> 'https://<site>/retrieve_messages.php'
export const api = (path) => `${API_BASE}/${String(path).replace(/^\/+/, '')}`;
