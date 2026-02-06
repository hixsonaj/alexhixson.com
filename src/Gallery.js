import './Gallery.css';
import { useEffect, useState } from "react";

export default function Gallery() {

  return (
    <div className='Gallery_Container'>
      <img
        className="photo"
        src={'/images/AlexHixson.jpg'}
        alt={'Alex Hixson'}/>
      <img
        className="photo"
        src={'/images/Image1.jpg'}
        alt={'BrockHampton'}/>
      <img
        className="photo"
        src={'/images/Image2.jpg'}
        alt={'RAHHH'}/>
      <img
        className="photo2"
        src={'/images/Image3.jpg'}
        alt={'MBAC'}/>
      <img
        className="photo"
        src={'/images/Image4.jpg'}
        alt={'Amaruism'}/>
      <img
        className="photo2"
        src={'/images/Image5.jpg'}
        alt={'The Water Sports Camp'}/>
      <img
        className="photo"
        src={'/images/Image6.jpg'}
        alt={'Drunk'}/>
      <img
        className="photo"
        src={'/images/Image7.jpg'}
        alt={'Gee Whizz'}/>
      <img
        className="photo2"
        src={'/images/Image8.jpg'}
        alt={'HEY SAILORS'}/>
      <img
        className="photo"
        src={'/images/Image9.jpg'}
        alt={'MBAC!!!'}/>
      <img
        className="photo2"
        src={'/images/Image10.jpg'}
        alt={'MBAC!!!!!'}/>
      <img
        className="photo2"
        src={'/images/Image11.jpg'}
        alt={'cute'}/>
      <img
        className="photo2"
        src={'/images/Image12.jpg'}
        alt={'oh hell nah'}/>
      <img
        className="photo2"
        src={'/images/Image13.jpg'}
        alt={'21!'}/>
      <img
        className="photo"
        src={'/images/Image.jpg'}
        alt={'Skiing'}/>
      <img
        className="photo2"
        src={'/images/Image14.jpg'}
        alt={'twenty one!'}/>
    </div>
  );
}