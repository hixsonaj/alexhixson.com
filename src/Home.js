import './Home.css';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, SITE_NAME_SHORT } from './config';
import RichText from './RichText';
import Replies from './Replies';
import { excerpt } from './postText';

const POSTS_PER_PAGE = 50;

export default function Home() {
  const [profileImg, setProfileImg] = useState(null);
  const [posts, setPosts] = useState([]);
  const [hasMore, setHasMore] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);

  useEffect(() => {
    fetch(api('profile_images.php'))
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
    fetch(api(`retrieve_messages.php?offset=0&limit=${POSTS_PER_PAGE}`))
      .then(res => res.json())
      .then(data => {
        setPosts(data.messages || []);
        setHasMore(data.hasMore || false);
      })
      .catch(() => {});
  }, []);

  function loadMore() {
    setLoadingMore(true);
    fetch(api(`retrieve_messages.php?offset=${posts.length}&limit=${POSTS_PER_PAGE}`))
      .then(res => res.json())
      .then(data => {
        setPosts(prev => [...prev, ...(data.messages || [])]);
        setHasMore(data.hasMore || false);
      })
      .catch(() => {})
      .finally(() => setLoadingMore(false));
  }

  function handleVote(pollId, optionIndex, postId) {
    fetch(api('submit_vote.php'), {
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
      {profileImg && <img className="Alex_Hixson" src={profileImg} alt={SITE_NAME_SHORT} />}
      <div className='Posts_Container'>
        {posts.length === 0 ? (
          <div className='Post'><p>No posts yet.</p></div>
        ) : (
          posts.map(post => (
            <div key={post.id} className={post.post_type === 'essay' ? 'Post Post_Essay' : 'Post'}>
              {post.post_type === 'essay' ? (
                <EssayPreview post={post} />
              ) : (
                <RichText text={post.message} />
              )}
              {post.post_type !== 'essay' && post.image_url && (
                <img className='Post_Image' src={post.image_url} alt="" />
              )}
              {post.poll && (
                <Poll poll={post.poll} onVote={(pollId, optIdx) => handleVote(pollId, optIdx, post.id)} />
              )}
              <span className='Post_Date'>
                {new Date(post.received_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
              </span>
              {post.post_type !== 'essay' && <Replies replies={post.replies} />}
            </div>
          ))
        )}
        {hasMore && (
          <button className='Load_More' onClick={loadMore} disabled={loadingMore}>
            {loadingMore ? 'LOADING...' : 'LOAD MORE'}
          </button>
        )}
      </div>
    </div>
  );
}

function EssayPreview({ post }) {
  const preview = excerpt(post.message, 280);
  return (
    <>
      <Link className='Essay_Preview_Title' to={`/essay/${post.id}`}>{post.title || 'Untitled'}</Link>
      {preview.text && <p className='Essay_Preview_Text'>{preview.text}{preview.truncated ? '…' : ''}</p>}
      <Link className='Essay_Read' to={`/essay/${post.id}`}>READ ENTRY →</Link>
      {post.replies && post.replies.length > 0 && (
        <span className='Essay_Reply_Count'>
          {post.replies.length} {post.replies.length === 1 ? 'reply' : 'replies'}
        </span>
      )}
    </>
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
