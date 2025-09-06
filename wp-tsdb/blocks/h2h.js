(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderH2H(el, team1, team2){
        const params = new URLSearchParams();
        if(team1){ params.append('team1', team1); }
        if(team2){ params.append('team2', team2); }
        fetch(apiBase + 'h2h?' + params.toString())
            .then(r => r.json())
            .then(data => {
                el.innerHTML = '';
                if(data && data.summary){
                    const s1 = data.summary[team1] || {};
                    const s2 = data.summary[team2] || {};
                    const div = document.createElement('div');
                    div.textContent = `${s1.wins||0}-${s1.draws||0}-${s1.losses||0} vs ${s2.wins||0}-${s2.draws||0}-${s2.losses||0}`;
                    el.appendChild(div);
                }
            })
            .catch(err => console.error('tsdb h2h fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-h2h').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const team1 = cfg.team1 || 0;
            const team2 = cfg.team2 || 0;
            const status = cfg.status || 'live';
            if(!team1 || !team2){ return; }
            const poll = () => renderH2H(el, team1, team2);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
