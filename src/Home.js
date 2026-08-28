import './Home.css';
import { useEffect, useState } from 'react';

export default function Home() {
  const [profileImg, setProfileImg] = useState(null);
  const [posts, setPosts] = useState([]);

  useEffect(() => {
    fetch('https://alexhixson.zerofour.tech/profile_images.php')
      .then(res => res.json())
      .then(data => {
        if (data.images && data.images.length > 0) {
          const random = data.images[Math.floor(Math.random() * data.images.length)];
          setProfileImg(random);
        }
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    fetch('https://alexhixson.zerofour.tech/retrieve_messages.php')
      .then(res => res.json())
      .then(data => setPosts(Array.isArray(data) ? data : (data.messages || [])))
      .catch(() => {});
  }, []);

  function handleVote(pollId, optionIndex, postId) {
    fetch('https://alexhixson.zerofour.tech/submit_vote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ poll_id: pollId, option_index: optionIndex }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setPosts(prev => prev.map(p => {
            if (p.id !== postId) return p;
            return { ...p, poll: { ...p.poll, votes: data.votes, user_voted: data.user_voted } };
          }));
        }
      })
      .catch(() => {});
  }

  return (
    <div className='Home_Container'>
      {profileImg && <img className="Alex_Hixson" src={profileImg} alt={'Alex Hixson'} />}
      <div className='Posts_Container'>
        {posts.length === 0 ? (
          <div className='Post'><p>No posts yet.</p></div>
        ) : (
          posts.map(post => (
            <div key={post.id} className='Post'>
              {post.message.split('\n').filter(l => l.trim() !== '').map((line, i) => (
                <p key={i}>{line}</p>
              ))}
              {post.image_url && (
                <img className='Post_Image' src={post.image_url} alt="" />
              )}
              {post.poll && (
                <Poll poll={post.poll} onVote={(pollId, optIdx) => handleVote(pollId, optIdx, post.id)} />
              )}
              <span className='Post_Date'>
                {new Date(post.received_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
              </span>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

function Poll({ poll, onVote }) {
  const total = poll.votes.reduce((a, b) => a + b, 0);
  const hasVoted = poll.user_voted !== null;

  if (hasVoted) {
    return (
      <div className='Poll'>
        {poll.options.map((option, i) => {
          const pct = total > 0 ? Math.round((poll.votes[i] / total) * 100) : 0;
          const isChosen = poll.user_voted === i;
          return (
            <div key={i} className='Poll_Result'>
              <div className='Poll_Result_Label'>
                <span>{option}</span>
                {isChosen && <span className='Poll_Check'>✓</span>}
                <span className='Poll_Pct'>{pct}%</span>
              </div>
              <div className='Poll_Bar_Track'>
                <div className='Poll_Bar_Fill' style={{ width: `${pct}%` }} />
              </div>
            </div>
          );
        })}
        <span className='Poll_Total'>{total} vote{total !== 1 ? 's' : ''}</span>
      </div>
    );
  }

  return (
    <div className='Poll'>
      {poll.options.map((option, i) => (
        <button key={i} className='Poll_Option' onClick={() => onVote(poll.id, i)}>
          {option}
        </button>
      ))}
      <span className='Poll_Total'>{total} vote{total !== 1 ? 's' : ''}</span>
    </div>
  );
}
