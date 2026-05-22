-- ============================================================
--  FEN-X Frizerski Salon -- Podatkovna baza
--  Ustvarjeno: 2025
-- ============================================================

-- Ustvari tabelo za termine
CREATE TABLE IF NOT EXISTS termini (
    id          SERIAL PRIMARY KEY,
    ime         TEXT NOT NULL,
    spol        TEXT NOT NULL,
    storitev    TEXT NOT NULL,
    opomba      TEXT,
    datum       TEXT NOT NULL,
    ura         TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW() NOT NULL
);

-- Indeksi za hitrejše iskanje
CREATE INDEX IF NOT EXISTS idx_termini_datum ON termini (datum);
CREATE INDEX IF NOT EXISTS idx_termini_datum_ura ON termini (datum, ura);

-- Primer testnih podatkov
INSERT INTO termini (ime, spol, storitev, opomba, datum, ura) VALUES
  ('Janez Novak',  'moški',  'Striženje las',    'Low taper fade',    '2025-05-05', '09:00'),
  ('Marko Kralj',  'moški',  'Brivanje',          NULL,                '2025-05-05', '10:00'),
  ('Ana Kovač',    'ženski', 'Barvanje las',       'Balayage',         '2025-05-06', '14:00');

-- Poizvedbe za admin panel
-- Vsi termini za določen dan:
--   SELECT * FROM termini WHERE datum = '2025-05-05' ORDER BY ura;
-- Zasedene ure za določen dan:
--   SELECT ura FROM termini WHERE datum = '2025-05-05';
-- Statistika:
--   SELECT COUNT(*) FILTER (WHERE datum = CURRENT_DATE::text) AS danes,
--          COUNT(*) FILTER (WHERE datum >= date_trunc('week', NOW())::date::text) AS ta_teden,
--          COUNT(*) AS skupaj
--   FROM termini;
