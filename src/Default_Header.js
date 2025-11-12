import { BrowserView, MobileView} from 'react-device-detect';
import { Outlet, Link } from "react-router-dom";
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBars, faXmark } from '@fortawesome/free-solid-svg-icons';
import { useState } from "react";
import './Default_Header.css';

export default function Default_Header() {
  const [show, setShow] = useState(false);
  return (
    <header>
      <BrowserView>
        <Link to="/"><h1 className='Header_Title'>ALEXHIXSON.COM</h1></Link>
        <ul className='Header_Navigation'>
          <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className='link'>AINT NO DAMN WAY</h3></Link></li> {/*Not sure why style cant go in css*/}
          <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className='link'>CLASSIC TIMELESS</h3></Link></li>
          <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className='link'>99.9%</h3></Link></li>
        </ul>
      </BrowserView>
      <MobileView>
        <Link to="/"><h1 className='Header_Title'>ALEX HIXSON</h1></Link>          
        <div className='Icon_Container'>
          <button className="Header_Navigation_Toggle_Button" onClick={() => setShow(!show)}>
            {show ? <FontAwesomeIcon icon={faXmark} /> : <FontAwesomeIcon icon={faBars} />}
          </button>
        </div>
        {show ? <Mobile_Navigation /> : null}
      </MobileView>
      <Outlet/>
    </header>
  );
}

function Mobile_Navigation() {
  return (
    <div className='Mobile_Navigation_Container'>
      <ul>
        <li><Link to="/" style={{ textDecoration: 'none' }}><h3 className='link'>AINT NO DAMN WAY</h3></Link></li> {/*Not sure why style cant go in css*/}
        <li><Link to="/" style={{ textDecoration: 'none' }}><h3 className='link'>CLASSIC TIMELESS</h3></Link></li>
        <li><Link to="/" style={{ textDecoration: 'none' }}><h3 className='link'>99.9%</h3></Link></li>
      </ul>
    </div>

  );
}

