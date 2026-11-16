-- Fixtures: invented notes, authors and comments, no real data. Accents, an apostrophe, an ampersand and a line break,
-- so that both hosts are seen escaping and encoding the same way; two notes on the same day (ordered by id then).
-- A note without author, an author without email, comments anonymous (author NULL) or unrated (rating NULL): the
-- repositories suite's null criteria.
SET NAMES utf8mb4;

INSERT INTO author (handle, name, email) VALUES
('ada', 'Ada Lovelace', 'ada@example.org'),
('basho', 'Matsuo Bashō', NULL),
('colette', 'Colette', 'colette@example.org');

INSERT INTO note (slug, title, body, topic, publishedAt, author_id) VALUES
('green-tea', 'Green tea at 70 degrees', 'Water just off the boil scalds the leaves.\nLet it rest a minute.', 'cuisine', '2026-03-02 08:30:00', (SELECT id FROM author WHERE handle = 'ada')),
('cafe-creme', 'Café crème & croissant', 'L''heure du café: a café crème, never after noon.', 'cuisine', '2026-03-05 07:15:00', (SELECT id FROM author WHERE handle = 'colette')),
('night-train', 'The night train', 'Couchettes of six, a window that does not open, <a> tags printed as text.', 'travel', '2026-02-11 22:00:00', (SELECT id FROM author WHERE handle = 'basho')),
('harbour-walk', 'A walk round the harbour', 'Twelve kilometres, two lighthouses, one ferry back.', 'travel', '2026-03-05 16:40:00', (SELECT id FROM author WHERE handle = 'ada')),
('tea-ceremony', 'Tea ceremony notes', 'Four guests, one bowl, the host''s silence.', 'cuisine', '2026-01-20 14:00:00', (SELECT id FROM author WHERE handle = 'basho')),
('first-snow', 'First snow', 'Überraschung: snow on the 1st of December, gone by noon.', 'season', '2025-12-01 09:00:00', NULL);

INSERT INTO comment (code, note_id, author_id, body, rating, state, createdAt)
SELECT c.code, n.id, a.id, c.body, c.rating, c.state, c.createdAt
FROM (
    SELECT 'c01' AS code, 'green-tea' AS note, 'basho' AS author, 'Seventy, not eighty.' AS body, 5 AS rating, 'published' AS state, '2026-03-02 09:00:00' AS createdAt
    UNION ALL SELECT 'c02', 'green-tea', 'colette', 'Et le thé vert japonais ?', NULL, 'published', '2026-03-02 10:00:00'
    UNION ALL SELECT 'c03', 'green-tea', NULL, 'Too hot for me.', 3, 'pending', '2026-03-03 08:00:00'
    UNION ALL SELECT 'c04', 'cafe-creme', 'ada', 'After noon, a noisette.', 4, 'published', '2026-03-05 08:00:00'
    UNION ALL SELECT 'c05', 'cafe-creme', 'basho', 'Cheap watches & croissants!', 2, 'spam', '2026-03-05 09:00:00'
    UNION ALL SELECT 'c06', 'night-train', 'colette', 'Les couchettes, quel souvenir.', 5, 'published', '2026-02-12 07:00:00'
    UNION ALL SELECT 'c07', 'night-train', 'ada', 'Which line?', NULL, 'pending', '2026-02-12 08:00:00'
    UNION ALL SELECT 'c08', 'harbour-walk', 'basho', 'An old pond, a ferry.', 4, 'published', '2026-03-06 07:00:00'
    UNION ALL SELECT 'c09', 'tea-ceremony', 'ada', 'One bowl for four?', 5, 'published', '2026-01-21 10:00:00'
    UNION ALL SELECT 'c10', 'tea-ceremony', 'colette', 'Le silence, oui.', 1, 'published', '2026-01-22 10:00:00'
    UNION ALL SELECT 'c11', 'tea-ceremony', NULL, 'Anonymous and unrated.', NULL, 'pending', '2026-01-23 10:00:00'
    UNION ALL SELECT 'c12', 'first-snow', 'basho', 'Snow on the 1st: haiku weather.', 3, 'published', '2025-12-01 12:00:00'
) AS c
JOIN note AS n ON n.slug = c.note
LEFT JOIN author AS a ON a.handle = c.author
ORDER BY c.code;
