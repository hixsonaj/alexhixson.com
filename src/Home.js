import './Home.css';
import { useEffect, useState } from "react";
import MessagesFeed from './Messages';

export default function Home() {

  return (
    <div className='Home_Container'>
      <img
        className="Alex_Hixson"
        src={'/images/AlexHixson.jpg'}
        alt={'Alex Hixson'}/>
      <h1>ALEXHIXSON.COM</h1>
      <p>HOLY WEBDEV!!!!!</p>
      <p><MessagesFeed messagesPerPage={5} enableLoadMore={false}/></p>
    </div>
  );
}