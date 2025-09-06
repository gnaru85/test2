(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderTeam(el, id){
        fetch(apiBase + 'team/' + id)
            .then(r => r.json())
            .then(data => {
                if(!data){ return; }
                const name = data.strTeam || data.team || data.name || '';
                el.textContent = name;
            })
            .catch(err => console.error('tsdb team fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-team').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const id = cfg.team || cfg.id || 0;
            const status = cfg.status || 'live';
            if(!id){ return; }
            const poll = () => renderTeam(el, id);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
