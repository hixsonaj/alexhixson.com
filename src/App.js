import { BrowserRouter, Routes, Route } from "react-router-dom";

import Header from './Default_Header.js';
import Footer from './Default_Footer.js';

import Home from './Home.js';
import Rome from './Rome.js';

import './App.css';

function App() {
  return (
    <body>
      <BrowserRouter>
      <Header/>
        <Routes>
          <Route index element={<Home />} />
          <Route path="*" element={<Home />} />
          <Route path="/Rome" element={<Rome />} />
        </Routes>
      <Footer/>
      </BrowserRouter>
    </body>
  );
}

export default App;
