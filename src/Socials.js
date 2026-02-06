import './Socials.css';
import { useEffect, useState } from "react";
import { Outlet, Link } from "react-router-dom";
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faInstagram, faGithub } from '@fortawesome/free-brands-svg-icons'
import { faEnvelope } from '@fortawesome/free-solid-svg-icons'

export default function Socials() {

  return (
    <div className='Socials_Container'>
      <a href="https://www.instagram.com/alex.hixson/"><FontAwesomeIcon icon={faInstagram} className="Icon_Instagram" />Instagram</a>
    </div>
  );
}