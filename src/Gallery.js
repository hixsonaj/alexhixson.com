import './Gallery.css';
import { useEffect, useState } from "react";

export default function Gallery() {
  const [images, setImages] = useState([]);

  useEffect(() => {
    fetch('https://alexhixson.zerofour.tech/gallery_images.php')
      .then(res => res.json())
      .then(data => setImages(data.images || []))
      .catch(() => setImages([]));
  }, []);

  return (
    <div className='Gallery_Container'>
      {images.map((img, i) => (
        <img
          key={i}
          className={img.landscape ? 'photo' : 'photo2'}
          src={img.url}
          alt=""
        />
      ))}
    </div>
  );
}
