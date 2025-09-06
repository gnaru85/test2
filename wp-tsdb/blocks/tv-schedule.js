(function(){
    const apiBase = (window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/') + 'tsdb/v1/';

    function renderTV(el, country){
        const params = new URLSearchParams();
        if(country){ params.append('country', country); }
        fetch(apiBase + 'tv?' + params.toString())
            .then(r => r.json())
            .then(data => {
                el.innerHTML = '';
                if(Array.isArray(data)){
                    data.forEach(item => {
                        const div = document.createElement('div');
                        const channel = item.channel || '';
                        const name = item.event || item.name || '';
                        div.textContent = channel + (name ? ': ' + name : '');
                        el.appendChild(div);
                    });
                }
            })
            .catch(err => console.error('tsdb tv fetch failed', err));
    }

    function init(){
        document.querySelectorAll('.tsdb-tv-schedule').forEach(el => {
            const cfg = JSON.parse(el.dataset.tsdb || '{}');
            const country = cfg.country || '';
            const status = cfg.status || 'live';
            const poll = () => renderTV(el, country);
            poll();
            const interval = (status === 'live' || status === 'inplay') ? 20000 : 30000;
            setInterval(poll, interval);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
