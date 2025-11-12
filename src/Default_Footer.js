import { Outlet, Link } from "react-router-dom";
import './Default_Footer.css';

export default function Default_Header() {
  return(
    <footer>
      <Link to="/" style={{ textDecoration: 'none' }}><h2 className='Footer_Title'>ALEXHIXSON.COM</h2></Link>          
      <ul className="Footer_Navigation">
        <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className="link">ERRMMMMMM</h3></Link></li> {/*Not sure why style cant go in css*/}
        <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className="link">67</h3></Link></li>
        <li><Link to="/Rome" style={{ textDecoration: 'none' }}><h3 className="link">YOU'VE GYATT TO BE RIZZIN ME</h3></Link></li>
      </ul>
      <Outlet/>
    </footer>
  );
}