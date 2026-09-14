import { tokenizeText } from './postText';

// Renders a post's text as paragraphs, with links. Each non-blank line becomes a
// <p>, matching how posts have always displayed.
export default function RichText({ text }) {
  return tokenizeText(text).map((line, i) => (
    <p key={i}>
      {line.map((t, j) =>
        t.type === 'link' ? (
          <a key={j} className='Post_Link' href={t.href} target='_blank' rel='noopener noreferrer'>
            {t.text}
          </a>
        ) : (
          <span key={j}>{t.value}</span>
        )
      )}
    </p>
  ));
}
