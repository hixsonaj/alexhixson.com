import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import "./Rome.css";

export default function Rome() {
  const navigate = useNavigate();

  useEffect(() => {
    const timer = setTimeout(() => {
      navigate("/"); // redirect back to home page
    }, 2000); // 2 seconds

    return () => clearTimeout(timer); // cleanup if component unmounts
  }, [navigate]);

  return (
    <div className="rome-container">
      <h1>All roads lead to Rome</h1>
    </div>
  );
}
