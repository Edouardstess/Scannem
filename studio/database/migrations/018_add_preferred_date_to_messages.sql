-- The contact form asks for a desired session date (spec §59). It was being
-- folded into the message body, which meant the photographer had to read the
-- text to find it and could not sort or filter on it.
ALTER TABLE messages ADD COLUMN preferred_date DATE NULL;

CREATE INDEX idx_messages_preferred_date ON messages (preferred_date);
