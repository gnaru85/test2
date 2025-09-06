(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderEvent(el, eventId){
        fetch(apiBase + 'event/' + eventId)
            .then(r => r.json())
            .then(data => {
                if(!data){ return; }
                const hs = data.home_score !== null && data.home_score !== undefined ? data.home_score : '';
                const as = data.away_score !== null && data.away_score !== undefined ? data.away_score : '';
                el.textContent = `${data.home_id} ${hs} - ${as} ${data.away_id}`;
            })
            .catch(err => console.error('tsdb event fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-live-event').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const eventId = cfg.event || cfg.id || 0;
            const status = cfg.status || 'live';
            if(!eventId){ return; }
            const poll = () => renderEvent(el, eventId);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
