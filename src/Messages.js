import React, { useEffect, useState } from "react";
import "./Messages.css";

export default function MessagesFeed({
  messagesPerPage = 10,
  enableLoadMore = true
}) {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState("");
  const [hasMore, setHasMore] = useState(false);
  const [offset, setOffset] = useState(0);

  const fetchMessages = async (currentOffset, append = false) => {
    try {
      if (append) {
        setLoadingMore(true);
      } else {
        setLoading(true);
      }

      const response = await fetch(
        `https://alexhixson.zerofour.tech/retrieve_messages.php?offset=${currentOffset}&limit=${messagesPerPage}`
      );

      if (!response.ok) {
        throw new Error(`HTTP error: ${response.status}`);
      }

      const data = await response.json();

      let messagesArray;
      let hasMoreMessages;
      
      if (Array.isArray(data)) {
        messagesArray = data;
        hasMoreMessages = data.length === messagesPerPage;
      } else {
        messagesArray = data.messages || [];
        hasMoreMessages = data.hasMore || false;
      }

      if (append) {
        setMessages((prev) => [...prev, ...messagesArray]);
      } else {
        setMessages(messagesArray);
      }

      setHasMore(enableLoadMore && hasMoreMessages);
    } catch (err) {
      setError("Failed to load messages.");
      console.error(err);
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  };

  useEffect(() => {
    fetchMessages(0);
  }, []);

  const loadMore = () => {
    const newOffset = offset + messagesPerPage;
    setOffset(newOffset);
    fetchMessages(newOffset, true);
  };

  if (loading) return <span>Loading messages...</span>;
  if (error) return <span style={{ color: "red" }}>{error}</span>;

  return (
    <div className="MessagesFeed_Container">
      <h2>Messages</h2>
      <p className="Get_Access">Get a message board access code!
        <p className="Message_Access">Email: messageaccess@zerofour.tech</p>
      </p>
      {messages.length === 0 ? (
        <p>No messages yet.</p>
      ) : (
        <>
          <div className="Messages_List">
            {messages.map((msg) => (
              <div key={msg.id} className="Message_Card">
                <div className="Message_Header">
                  <strong>{msg.sender_name}</strong>
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

          {hasMore &&  (
            <div className="LoadMore_Container" style={{ textAlign: "center", margin: "20px 0" }}>
              <button
                onClick={loadMore}
                disabled={loadingMore}
                style={{
                  padding: "10px 20px",
                  fontSize: "16px",
                  cursor: loadingMore ? "not-allowed" : "pointer",
                  opacity: loadingMore ? 0.6 : 1
                }}
              >
                {loadingMore ? "Loading..." : "Load More"}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}