(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderStandings(el, league, season){
        const params = new URLSearchParams();
        if(league){ params.append('league', league); }
        if(season){ params.append('season', season); }
        fetch(apiBase + 'standings?' + params.toString())
            .then(r => r.json())
            .then(data => {
                el.innerHTML = '';
                if(Array.isArray(data)){
                    data.forEach(t => {
                        const item = document.createElement('div');
                        const name = t.name || t.team || t.teamname || t.strTeam || t.teamid || '';
                        const pts = (t.points !== undefined ? t.points : (t.P !== undefined ? t.P : ''));
                        item.textContent = name + (pts !== '' ? ' (' + pts + ')' : '');
                        el.appendChild(item);
                    });
                }
            })
            .catch(err => console.error('tsdb standings fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-live-standings').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const league = cfg.league || 0;
            const season = cfg.season || '';
            const status = cfg.status || 'live';
            const poll = () => renderStandings(el, league, season);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
