import './Gallery.css';
import { useEffect, useState } from "react";

export default function Gallery() {

  return (
    <div className='Gallery_Container'>
      <img
        className="photo"
        src={'/images/alexhixson.jpg'}
        alt={'Alex Hixson'}/>
      <img
        className="photo"
        src={'/images/image1.jpg'}
        alt={'BrockHampton'}/>
      <img
        className="photo"
        src={'/images/image2.jpg'}
        alt={'RAHHH'}/>
      <img
        className="photo2"
        src={'/images/image3.jpg'}
        alt={'MBAC'}/>
      <img
        className="photo"
        src={'/images/image4.jpg'}
        alt={'Amaruism'}/>
      <img
        className="photo2"
        src={'/images/image5.jpg'}
        alt={'The Water Sports Camp'}/>
      <img
        className="photo"
        src={'/images/image6.jpg'}
        alt={'Drunk'}/>
      <img
        className="photo"
        src={'/images/image7.jpg'}
        alt={'Gee Whizz'}/>
      <img
        className="photo2"
        src={'/images/image8.jpg'}
        alt={'HEY SAILORS'}/>
      <img
        className="photo"
        src={'/images/image9.jpg'}
        alt={'MBAC!!!'}/>
      <img
        className="photo2"
        src={'/images/image10.jpg'}
        alt={'MBAC!!!!!'}/>
      <img
        className="photo2"
        src={'/images/image11.jpg'}
        alt={'cute'}/>
      <img
        className="photo2"
        src={'/images/image12.jpg'}
        alt={'oh hell nah'}/>
      <img
        className="photo2"
        src={'/images/image13.jpg'}
        alt={'21!'}/>
      <img
        className="photo"
        src={'/images/image.jpg'}
        alt={'Skiing'}/>
      <img
        className="photo2"
        src={'/images/image14.jpg'}
        alt={'twenty one!'}/>
    </div>
  );
}