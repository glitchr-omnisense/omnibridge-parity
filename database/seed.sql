-- Fixtures: invented notes, no real data. Accents, an apostrophe, an ampersand and a line break, so that both hosts
-- are seen escaping and encoding the same way; two notes on the same day (ordered by id then).
SET NAMES utf8mb4;

INSERT INTO note (slug, title, body, topic, publishedAt) VALUES
('green-tea', 'Green tea at 70 degrees', 'Water just off the boil scalds the leaves.\nLet it rest a minute.', 'cuisine', '2026-03-02 08:30:00'),
('cafe-creme', 'Café crème & croissant', 'L''heure du café: a café crème, never after noon.', 'cuisine', '2026-03-05 07:15:00'),
('night-train', 'The night train', 'Couchettes of six, a window that does not open, <a> tags printed as text.', 'travel', '2026-02-11 22:00:00'),
('harbour-walk', 'A walk round the harbour', 'Twelve kilometres, two lighthouses, one ferry back.', 'travel', '2026-03-05 16:40:00'),
('tea-ceremony', 'Tea ceremony notes', 'Four guests, one bowl, the host''s silence.', 'cuisine', '2026-01-20 14:00:00'),
('first-snow', 'First snow', 'Überraschung: snow on the 1st of December, gone by noon.', 'season', '2025-12-01 09:00:00');
