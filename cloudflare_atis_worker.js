/**
 * Cloudflare Worker — Proxy ATIS EBBR
 * Déploiement : https://workers.cloudflare.com (gratuit, 100k req/jour)
 * 
 * Ce worker :
 *   1. Fetch la page atis.guru/atis/EBBR
 *   2. Parse le texte ATIS (ARR RWY / DEP RWY / vent)
 *   3. Retourne un JSON propre avec les headers CORS
 * 
 * URL d'accès après déploiement :
 *   https://ton-worker.ton-sous-domaine.workers.dev/atis/EBBR
 */

export default {
  async fetch(request) {

    // Headers CORS pour autoriser piste01casuffit.be
    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, OPTIONS',
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'public, max-age=300', // cache 5 min
    };

    // Preflight CORS
    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders });
    }

    try {
      // Fetch la page atis.guru
      const resp = await fetch('https://atis.guru/atis/EBBR', {
        headers: {
          'User-Agent': 'Mozilla/5.0 (compatible; piste01casuffit.be/meteo)',
          'Accept': 'text/html',
        },
        cf: { cacheTtl: 300 } // cache Cloudflare 5 min
      });

      const html = await resp.text();

      // ── Parser le texte ATIS ─────────────────────────────────────────────
      // Format typique EBBR :
      // EBBR ARR ATIS L 1950Z EBBR NAT, EXP VECT FOR ILS APP ARR RWY25L ... DEP RWY25R ...
      // EBBR DEP ATIS N 1820Z EBBR NAT, DEP RWY25R ... ARR RWY25L ...

      // Extraire les blocs ATIS bruts (texte après les balises HTML)
      // Supprimer les balises HTML
      const text = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ');

      // Chercher ARR ATIS et DEP ATIS
      const arrMatch = text.match(/EBBR\s+ARR\s+ATIS\s+(\w+)\s+(\d{4})Z([^E]{0,500})/i);
      const depMatch = text.match(/EBBR\s+DEP\s+ATIS\s+(\w+)\s+(\d{4})Z([^E]{0,500})/i);

      // Parser une entrée ATIS
      function parseAtis(match, type) {
        if (!match) return null;
        const letter = match[1];
        const time   = match[2]; // ex: "1950"
        const body   = match[3];

        // Piste arrivée
        const arrRwy = body.match(/ARR\s+RWY\s*(\d{2}[LR]?)/i);
        // Piste départ
        const depRwy = body.match(/DEP\s+RWY\s*(\d{2}[LR]?)/i);
        // Vent (ex: 230/19KT ou VRB02KT)
        const wind   = body.match(/(\d{3}|VRB)\/(\d+)(?:G(\d+))?KT/i) ||
                       body.match(/(VRB)(\d+)KT/i);
        // QNH
        const qnh    = body.match(/QNH\s*(\d{4})\s*HPA/i) ||
                       body.match(/Q\s*(\d{4})/i);
        // Texte brut
        const raw    = ('EBBR ' + type + ' ATIS ' + letter + ' ' + time + 'Z ' + body).trim().substring(0, 400);

        return {
          type,           // "ARR" ou "DEP"
          letter,
          time_utc: time,
          arr_rwy:  arrRwy ? arrRwy[1] : null,
          dep_rwy:  depRwy ? depRwy[1] : null,
          wind_dir: wind ? (wind[1] === 'VRB' ? 'VRB' : parseInt(wind[1])) : null,
          wind_spd: wind ? parseInt(wind[2]) : null,
          wind_gst: wind && wind[3] ? parseInt(wind[3]) : null,
          qnh:      qnh ? parseInt(qnh[1]) : null,
          raw,
        };
      }

      const arr = parseAtis(arrMatch, 'ARR');
      const dep = parseAtis(depMatch, 'DEP');

      // Piste en service = piste ARR (atterrissage), sinon DEP
      const active_rwy_arr = arr?.arr_rwy || dep?.arr_rwy || null;
      const active_rwy_dep = arr?.dep_rwy || dep?.dep_rwy || null;

      const result = {
        source:         'atis.guru/atis/EBBR',
        fetched_at:     new Date().toISOString(),
        arr_atis:       arr,
        dep_atis:       dep,
        // Résumé rapide
        active_rwy_arr,   // Piste atterrissage annoncée
        active_rwy_dep,   // Piste décollage annoncée
        ok: !!(arr || dep),
      };

      return new Response(JSON.stringify(result, null, 2), {
        status: 200,
        headers: corsHeaders,
      });

    } catch (err) {
      return new Response(JSON.stringify({
        ok: false,
        error: err.message,
        source: 'atis.guru/atis/EBBR',
      }), {
        status: 500,
        headers: corsHeaders,
      });
    }
  }
};
