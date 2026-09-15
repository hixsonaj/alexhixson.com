import RichText from './RichText';

// Replies under a post, oldest first. Each shows its own date and time, since
// replies to one post often land on the same day.
export default function Replies({ replies }) {
  if (!replies || replies.length === 0) return null;
  return (
    <div className='Replies'>
      {replies.map(reply => (
        <div key={reply.id} className='Reply'>
          <RichText text={reply.message} />
          {reply.image_url && <img className='Reply_Image' src={reply.image_url} alt='' />}
          <span className='Reply_Date'>{formatWhen(reply.received_at)}</span>
        </div>
      ))}
    </div>
  );
}

function formatWhen(value) {
  // MySQL gives "2026-09-14 20:27:08"; the T form parses in every browser.
  const d = new Date(String(value).replace(' ', 'T'));
  if (isNaN(d)) return '';
  const date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const time = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  return `${date} · ${time}`;
}
