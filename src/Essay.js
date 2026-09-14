import './Essay.css';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from './config';
import RichText from './RichText';

export default function Essay() {
  const { id } = useParams();
  const [essay, setEssay] = useState(null);
  const [status, setStatus] = useState('loading');

  useEffect(() => {
    setStatus('loading');
    fetch(api(`retrieve_messages.php?id=${encodeURIComponent(id)}`))
      .then(res => res.json())
      .then(data => {
        const post = (data.messages || [])[0];
        if (post) {
          setEssay(post);
          setStatus('ready');
        } else {
          setStatus('missing');
        }
      })
      .catch(() => setStatus('error'));
  }, [id]);

  if (status === 'loading') {
    return <div className='Essay_Container'><p className='Essay_Status'>Loading…</p></div>;
  }
  if (status !== 'ready') {
    return (
      <div className='Essay_Container'>
        <p className='Essay_Status'>
          {status === 'missing' ? "That essay doesn't exist." : "Couldn't load this essay. Try refreshing."}
        </p>
        <Link className='Essay_Back' to='/'>← BACK TO POSTS</Link>
      </div>
    );
  }

  return (
    <article className='Essay_Container'>
      <Link className='Essay_Back' to='/'>← BACK TO POSTS</Link>
      <h1 className='Essay_Title'>{essay.title || 'Untitled'}</h1>
      <span className='Essay_Date'>
        {new Date(essay.received_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
      </span>
      <div className='Essay_Body'>
        <RichText text={essay.message} />
      </div>
      {essay.image_url && <img className='Essay_Image' src={essay.image_url} alt='' />}
    </article>
  );
}
