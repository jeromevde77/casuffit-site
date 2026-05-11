/**
 * Cloudflare Worker — Proxy API BATC statistiques mouvements
 * Déploiement : https://workers.cloudflare.com (gratuit, 100k req/jour)
 *
 * Ce worker contourne le blocage CORS de l'API BATC en proxifiant
 * les requêtes depuis Cloudflare avec les bons headers navigateur.
 *
 * URL après déploiement :
 *   https://ton-worker.workers.dev/batc-stats?date=1778364000&aggregate=day
 */

export default {
  async fetch(request) {

    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, OPTIONS',
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'public, max-age=600', // cache 10 min
    };

    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders });
    }

    const url    = new URL(request.url);
    const date   = url.searchParams.get('date')      || Math.floor(Date.now()/1000);
    const agg    = url.searchParams.get('aggregate') || 'day';
    const filter = url.searchParams.get('departures_arrivals') || 'departures_arrivals';

    const batcUrl = `https://www.batc.be/fr/api/visualisation/statistics_airport_movements`
      + `?time_of_day=day_night`
      + `&aggregate=${agg}`
      + `&date=${date}`
      + `&departures_arrivals=${filter}`;

    try {
      const resp = await fetch(batcUrl, {
        headers: {
          'User-Agent':      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
          'Accept':          'application/json, text/plain, */*',
          'Accept-Language': 'fr-BE,fr;q=0.9',
          'Referer':         'https://www.batc.be/fr/pistes-en-usage/statistiques',
          'Origin':          'https://www.batc.be',
        },
        cf: { cacheTtl: 600 }
      });

      const body = await resp.text();

      // Vérifier que c'est du JSON valide
      try {
        JSON.parse(body);
      } catch(e) {
        return new Response(JSON.stringify({
          ok: false,
          error: 'BATC response non-JSON: ' + body.slice(0, 200),
        }), { status: 502, headers: corsHeaders });
      }

      return new Response(body, {
        status: resp.status,
        headers: corsHeaders,
      });

    } catch(err) {
      return new Response(JSON.stringify({
        ok: false,
        error: err.message,
      }), { status: 500, headers: corsHeaders });
    }
  }
};
