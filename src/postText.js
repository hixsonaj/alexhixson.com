// Turns the plain text of an emailed post into text and link pieces.
//
// Syntax, typed into the email body:
//   [google](google.com)            -> "google", linking to https://google.com
//   https://example.com/page        -> linked as itself
//   www.example.com                 -> linked as itself
//
// No React and no HTML here: this returns plain objects, and the caller renders
// them as elements. Nothing from an email is ever inserted as HTML, so a post
// can't inject markup or script.
//
// Outlook quirk: when you type a web address, Outlook turns it into a hyperlink,
// and the plain-text copy it sends appends the target in angle brackets:
//   [google](www.google.com<http://www.google.com/>)
//   www.google.com<http://www.google.com/>
// Those <...> echoes are recognized and dropped.

// [label](target) — optionally with Outlook's <url> echo before the closing paren.
const MARKDOWN_LINK = /\[([^\]\n]+)\]\(\s*([^)\s<>]+)\s*(?:<[^>\n]*>)?\s*\)/y;

// A bare address, optionally followed by Outlook's <url> echo.
const BARE_URL = /(?:https?:\/\/|www\.)[^\s<>()\[\]]+(?:\s?<https?:\/\/[^>\s]*>)?/iy;

// Punctuation that ends a sentence rather than belonging to the address.
const TRAILING_PUNCT = /[.,!?;:'"]+$/;

/**
 * Normalize a link target to a safe absolute URL, or null if it can't be one.
 * Only http and https are allowed — a javascript: or data: target is refused and
 * the text is left unlinked.
 */
export function safeHref(raw) {
  const target = String(raw || '').trim();
  if (!target) return null;

  const scheme = target.match(/^([a-z][a-z0-9+.-]*):/i);
  let url;
  if (scheme) {
    if (!/^https?$/i.test(scheme[1])) return null;
    url = target;
  } else {
    url = 'https://' + target.replace(/^\/+/, '');
  }

  try {
    const parsed = new URL(url);
    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return null;
    if (!parsed.hostname.includes('.')) return null;
    return parsed.href;
  } catch {
    return null;
  }
}

/** Split one line of text into [{type:'text', value}] and [{type:'link', text, href}]. */
export function tokenizeLine(line) {
  const tokens = [];
  let buffer = '';
  let i = 0;

  const flush = () => {
    if (buffer) tokens.push({ type: 'text', value: buffer });
    buffer = '';
  };

  while (i < line.length) {
    const ch = line[i];

    if (ch === '[') {
      MARKDOWN_LINK.lastIndex = i;
      const m = MARKDOWN_LINK.exec(line);
      const href = m && safeHref(m[2]);
      if (m && href) {
        flush();
        tokens.push({ type: 'link', text: m[1].trim(), href });
        i = MARKDOWN_LINK.lastIndex;
        continue;
      }
    }

    // Only start a bare URL at a word boundary, so "xwww.foo" isn't split.
    if ((ch === 'h' || ch === 'H' || ch === 'w' || ch === 'W') && (i === 0 || /[\s(]/.test(line[i - 1]))) {
      BARE_URL.lastIndex = i;
      const m = BARE_URL.exec(line);
      if (m) {
        const echo = m[0].match(/\s?<https?:\/\/[^>\s]*>$/);
        let shown = echo ? m[0].slice(0, -echo[0].length) : m[0];
        const trail = shown.match(TRAILING_PUNCT);
        if (trail) shown = shown.slice(0, -trail[0].length);

        const href = safeHref(shown);
        if (href) {
          flush();
          tokens.push({ type: 'link', text: shown, href });
          // Consume the echo, but hand trailing punctuation back as text.
          i += echo ? m[0].length : shown.length;
          if (echo && trail) buffer += trail[0];
          continue;
        }
      }
    }

    buffer += ch;
    i += 1;
  }

  flush();
  return tokens;
}

/** Split a whole post into lines, dropping blank ones, each tokenized. */
export function tokenizeText(text) {
  return String(text || '')
    .replace(/\r\n?/g, '\n')
    .split('\n')
    .filter((line) => line.trim() !== '')
    .map(tokenizeLine);
}

/** Plain-text rendering, with links reduced to their labels. */
export function toPlainText(text) {
  return tokenizeText(text)
    .map((line) => line.map((t) => (t.type === 'link' ? t.text : t.value)).join(''))
    .join(' ');
}

/** First ~maxChars of plain text, cut at a word boundary. */
export function excerpt(text, maxChars = 280) {
  const plain = toPlainText(text).replace(/\s+/g, ' ').trim();
  if (plain.length <= maxChars) return { text: plain, truncated: false };
  const cut = plain.slice(0, maxChars);
  const lastSpace = cut.lastIndexOf(' ');
  return {
    text: (lastSpace > maxChars * 0.6 ? cut.slice(0, lastSpace) : cut).replace(/[\s.,;:!?-]+$/, ''),
    truncated: true,
  };
}
