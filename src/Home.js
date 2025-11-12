import './Home.css';
import { useEffect, useState } from "react";

export default function Home() {

  useEffect(() => {
  fetch("http://alexhixson.zerofour.tech/messages.txt")
    .then(res => res.text())
    .then(text => setMessages(text))
    .catch(err => console.error(err));
  }, []);

  const [messages, setMessages] = useState("");

  return (
    <div className='Home_Container'>
      <img
        className="Alex_Hixson"
        src={'/images/are_you_serious.jpg'}
        alt={'are_you_serious'}/>
      <h1>ALEXHIXSON.COM!!!!!!!!!!</h1>
      <p>LETS GOOOOO</p>
      <p>HOLY WEBDEV!!!!!</p>
      <p>{messages}</p>
    </div>
  );
}