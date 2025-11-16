import React, { useEffect, useState } from "react";
import "./Messages.css"; // optional

export default function MessagesFeed() {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const fetchMessages = async () => {
      try {
        const response = await fetch("https://alexhixson.zerofour.tech/retrieve_messages.php");

        if (!response.ok) {
          throw new Error(`HTTP error: ${response.status}`);
        }

        const data = await response.json();
        setMessages(data);
      } catch (err) {
        setError("Failed to load messages.");
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchMessages();
  }, []);

  if (loading) return <span>Loading messages...</span>;
  if (error) return <span style={{ color: "red" }}>{error}</span>;

  return (
    <div className="MessagesFeed_Container">
      <h2>Messages</h2>

      {messages.length === 0 ? (
        <p>No messages yet.</p>
      ) : (
        <div className="Messages_List">
          {messages.map((msg) => (
            <div key={msg.id} className="Message_Card">
              <div className="Message_Header">
                <strong>{msg.sender_name}</strong>{" "}
              </div>

              <div className="Message_Subject">
                <strong>Subject:</strong> {msg.subject}
              </div>

              <div className="Message_Body">
                {msg.message.split("\n").map((line, i) => (
                  <p key={i}>{line}</p>
                ))}
              </div>

              <div className="Message_Date">
                {new Date(msg.received_at).toLocaleString()}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
