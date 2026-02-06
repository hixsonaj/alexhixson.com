import { Outlet, Link } from "react-router-dom";
import './Default_Footer.css';

export default function Default_Header() {
  return(
    <footer>
      <div className="Footer_Title_Container">
        <Link to="/" style={{ textDecoration: 'none' }}><h2 className='Footer_Title'>ALEXHIXSON.COM</h2></Link>   
      </div>
      <ul className="Footer_Navigation">
        <li><Link to="/Socials" style={{ textDecoration: 'none' }}><h3 className="link">SOCIALS</h3></Link></li> {/*Not sure why style cant go in css*/}
        <li><Link to="/Messages" style={{ textDecoration: 'none' }}><h3 className="link">MESSAGES</h3></Link></li>
        <li><Link to="/Gallery" style={{ textDecoration: 'none' }}><h3 className="link">GALLERY</h3></Link></li>
      </ul>
      <Outlet/>
    </footer>
  );
}