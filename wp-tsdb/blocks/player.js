(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderPlayer(el, id){
        fetch(apiBase + 'player/' + id)
            .then(r => r.json())
            .then(data => {
                if(!data){ return; }
                const name = data.strPlayer || data.player || data.name || '';
                el.textContent = name;
            })
            .catch(err => console.error('tsdb player fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-player').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const id = cfg.player || cfg.id || 0;
            const status = cfg.status || 'live';
            if(!id){ return; }
            const poll = () => renderPlayer(el, id);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
