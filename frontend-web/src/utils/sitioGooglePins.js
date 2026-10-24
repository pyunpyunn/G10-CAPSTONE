// Place coordinates researched on 2026-10-22. These are reference points only;
// never feed this catalog into household classification or boundary imports.
const place = (latitude, longitude, title, address, cid, note) => ({
  latitude, longitude, title, address, note, researchedAt: '2026-10-22',
  sourceUrl: `https://www.google.com/maps?cid=${cid}`,
})

export const sitioGooglePins = [
  {"id": "camansi", "name": "Camansi", "candidateNotice": "Google lists this Camansi in Kinasang-an Pardo. Its connection to Mambaling’s Camansi is unconfirmed.", "candidates": [{"title": "Camansi", "address": "7VP4+C2W Camansi, Kinasang-an Pardo, Cebu City, 6000 Cebu", "latitude": 10.2861181, "longitude": 123.8550809, "id": "5693486887132621654", "sourceUrl": "https://www.google.com/maps?cid=5693486887132621654", "researchedAt": "2026-10-22"}]},
  { id: 'paglaum', name: 'Paglaum', plusCode: '8V6P+WF Cebu City, Cebu', ...place(10.3123253, 123.8862491, 'Sitio Paglaum', 'V Rama Ave, Cebu City, 6000 Cebu', '1377466696183318961', 'Google Maps listing supplied by the user. This reference pin is shown at the Google result location, including outside the barangay outline.') },
  { id: 'club', name: 'Club', unavailable: 'No matching Google pin established. Public records use the combined name Club Kawayan.' },
  {"id": "kawayan", "name": "Kawayan", "candidateNotice": "Google returned similarly named places. Neither is confirmed as Mambaling’s Club Kawayan.", "candidates": [{"title": "Sitio Kawayan Residences", "address": "Sitio Kawayan Residences, Busay, Cebu City, 6000 Cebu", "latitude": 10.3499489, "longitude": 123.8891168, "id": "10377377110332260220", "sourceUrl": "https://www.google.com/maps?cid=10377377110332260220", "researchedAt": "2026-10-22"}, {"title": "SITIO KAWAYAN CHAPEL", "address": "7VJ2+PG2 SITIO KAWAYAN CHAPEL, Cebu City, 6000 Cebu", "latitude": 10.2817543, "longitude": 123.8512806, "id": "11606411810371225608", "sourceUrl": "https://www.google.com/maps?cid=11606411810371225608", "researchedAt": "2026-10-22", "plusCode": "7VJ2+PG2"}]},
  {"id": "kolo", "name": "Kolo", "candidateNotice": "Google lists these places in Lapu-Lapu and Talisay. Neither is confirmed as Mambaling’s Kolo.", "candidates": [{"title": "SITIO KOLO", "address": "7XGH+22Q SITIO KOLO, Lapu-Lapu, Cebu", "latitude": 10.2750832, "longitude": 123.9776042, "id": "11627603300981526017", "sourceUrl": "https://www.google.com/maps?cid=11627603300981526017", "researchedAt": "2026-10-22"}, {"title": "Sitio Colo", "address": "Sitio Colo, Inayawan Sports Complex, Sitio Colo, F. Jaca St, Talisay, Cebu", "latitude": 10.2688353, "longitude": 123.855262, "id": "5336067005303207493", "sourceUrl": "https://www.google.com/maps?cid=5336067005303207493", "researchedAt": "2026-10-22"}]},
  { id: 'larima', name: 'Larima', ...place(10.2948409, 123.8791363, 'Larima Mambaling Homeowners Association', '237 Larima St, Mambaling, Cebu City', '5439189471944924854', 'Homeowners association reference point. This pin is outside the stored indicative barangay outline; the source address names Mambaling.') },
  { id: 'lawis', name: 'Lawis', ...place(10.2880266, 123.8806039, 'Lawis Alaska Mambaling Cebu City Philippines', 'Lawis Alaska, Mambaling, Cebu City', '13143145446609449095', 'Google Maps place listing for Lawis Alaska.') },
  { id: 'manga', name: 'Manga', ...place(10.2892999, 123.8781814, 'Mambaling Cebu City', '406 Main Rd, Sitio Manga, Mambaling, Cebu City', '1517692800786653094', 'Address reference within Sitio Manga; not an official sitio center.') },
  {"id": "manggasan", "name": "Manggasan", "candidateNotice": "Google returned Sitio Mangga San Roque for Manggasan. This name match is unconfirmed.", "candidates": [{"title": "248-X Sitio Mangga San Roque", "address": "7VQH+P94 248-X Sitio Mangga San Roque, Cebu City, 6000 Cebu", "latitude": 10.2892625, "longitude": 123.8784531, "id": "3820140353994659384", "sourceUrl": "https://www.google.com/maps?cid=3820140353994659384", "researchedAt": "2026-10-22", "plusCode": "7VQH+P94"}]},
  {"id": "san", "name": "San", "candidateNotice": "San is incomplete: choose the actual Google result to view. Neither is confirmed as the intended sitio.", "candidates": [{"title": "San Juan", "address": "7VQM+QMH San Juan, badjawan, 6000 Cebu", "latitude": 10.289428, "longitude": 123.8841964, "id": "9543713594786635167", "sourceUrl": "https://www.google.com/maps?cid=9543713594786635167", "researchedAt": "2026-10-22", "plusCode": "7VQM+QMH"}, {"title": "San Roque Rd", "address": "San Roque Rd, Mambaling, Cebu City, 6000 Cebu", "latitude": 10.2895854, "longitude": 123.8763758, "id": "1430105002068006327", "sourceUrl": "https://www.google.com/maps?cid=1430105002068006327", "researchedAt": "2026-10-22"}]},
  { id: 'tromar', name: 'Tromar', ...place(10.2903804, 123.8779543, '664 B', '664-B, Tromar, F. Vestil St, Mambaling, Cebu City', '1225294149079693663', 'Address reference in Tromar; not an official sitio center.') },
  {"id": "tupas", "name": "Tupas", "candidateNotice": "Google returned Tupas St in Cebu City. This street reference is not confirmed as the intended sitio.", "candidates": [{"title": "Tupas St", "address": "Tupas St, Cebu City, 6000 Cebu", "latitude": 10.2923585, "longitude": 123.8911082, "id": "11224806431228396653", "sourceUrl": "https://www.google.com/maps?cid=11224806431228396653", "researchedAt": "2026-10-22"}]},
  { id: 'viking', name: 'Viking', ...place(10.2897254, 123.8806118, '210 H Alaska Viking Mambaling', '19 Ybanez Compound Rd, Cebu City', '12741785657808034142', 'Housing development reference bearing the Viking Mambaling name.') },
  { id: 'ybanez', name: 'Ybanez Compounds', latitude: 10.2892956, longitude: 123.8801145, title: 'Ybanez Compound Rd', address: 'Mambaling, Cebu City', note: 'Road reference point; the compound extent and center are not established.', researchedAt: '2026-10-22', sourceUrl: 'https://www.google.com/maps/search/?api=1&query=Ybanez+Compound+Rd+Mambaling+Cebu+City' },
]

export const sitioSearchUrl = sitio => sitio.sourceUrl || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${sitio.name} Mambaling Cebu City`)}`

// Ambiguous Google results are plotted only after the user selects a listing.
export function resolveSitioPin(sitioId, resultId = '') {
  const sitio = sitioGooglePins.find(item => item.id === sitioId)
  if (!sitio || sitio.unavailable) return null
  if (!sitio.candidates) return sitio
  const result = sitio.candidates.find(item => item.id === resultId)
  return result ? { ...result, id: `${sitio.id}:${result.id}`, name: sitio.name, candidate: true, note: sitio.candidateNotice } : null
}
