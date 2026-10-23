import RichText from './RichText';

// Comments that arrived with a forward. They sit above the post they belong to,
// because a forward is a later thought about an older post — newest first.
export default function Notes({ notes }) {
  if (!notes || notes.length === 0) return null;
  return (
    <div className='Notes'>
      {notes.map(note => (
        <div key={note.id} className='Note'>
          <RichText text={note.message} />
          {note.image_url && <img className='Note_Image' src={note.image_url} alt='' />}
          <span className='Note_Date'>{formatWhen(note.received_at)}</span>
        </div>
      ))}
    </div>
  );
}

function formatWhen(value) {
  const d = new Date(String(value).replace(' ', 'T'));
  if (isNaN(d)) return '';
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
