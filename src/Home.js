import './Home.css';
import { useEffect, useState } from "react";
import MessagesFeed from './Messages';

export default function Home() {

  return (
    <div className='Home_Container'>
      <img
        className="Alex_Hixson"
        src={'/images/are_you_serious.jpg'}
        alt={'are_you_serious'}/>
      <h1>ALEXHIXSON.COM!!!!!!!!!!</h1>
      <p>LETS GOOOOO</p>
      <p>HOLY WEBDEV!!!!!</p>
      <p><MessagesFeed/></p>
    </div>
  );
}