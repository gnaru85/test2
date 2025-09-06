(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';
    const nonce = window.wpApiSettings ? window.wpApiSettings.nonce : '';

    function renderFixtures(el, league, status){
        const params = new URLSearchParams();
        if(league){ params.append('league', league); }
        if(status){ params.append('status', status); }
        fetch(apiBase + 'fixtures?' + params.toString(), { headers: { 'X-WP-Nonce': nonce } })
            .then(r => r.json())
            .then(data => {
                el.innerHTML = '';
                if(Array.isArray(data)){
                    data.forEach(f => {
                        const item = document.createElement('div');
                        const hs = f.home_score !== null && f.home_score !== undefined ? f.home_score : '';
                        const as = f.away_score !== null && f.away_score !== undefined ? f.away_score : '';
                        item.textContent = `${f.home_id} ${hs} - ${as} ${f.away_id}`;
                        el.appendChild(item);
                    });
                }
            })
            .catch(err => console.error('tsdb fixtures fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-live-fixtures').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const league = cfg.league || 0;
            const status = cfg.status || 'live';
            const poll = () => renderFixtures(el, league, status);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
