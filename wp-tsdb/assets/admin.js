(function($){
    const apiBase = tsdbAdmin.rest;

    async function populateCountries(){
        const res = await fetch(apiBase + 'countries');
        const data = await res.json();
        const select = $('#tsdb_country').empty();
        select.append('<option value="">Select Country</option>');
        data.forEach(c => {
            const name = c.name_en || c.name || c.strCountry;
            select.append(`<option value="${name}">${name}</option>`);
        });
    }

    async function populateSports(){
        const res = await fetch(apiBase + 'sports');
        const data = await res.json();
        const select = $('#tsdb_sport').empty();
        select.append('<option value="">Select Sport</option>');
        data.forEach(s => {
            const name = s.strSport || s.name;
            select.append(`<option value="${name}">${name}</option>`);
        });
    }

    async function populateLeagues(){
        const country = $('#tsdb_country').val();
        const sport = $('#tsdb_sport').val();
        if(!country || !sport){ return; }
        const res = await fetch(`${apiBase}leagues?country=${encodeURIComponent(country)}&sport=${encodeURIComponent(sport)}`);
        const data = await res.json();
        const select = $('#tsdb_league').empty();
        select.append('<option value="">Select League</option>');
        data.forEach(l => {
            const id = l.idLeague || l.id;
            const name = l.strLeague || l.name;
            select.append(`<option value="${id}">${name}</option>`);
        });
    }

    async function populateSeasons(){
        const league = $('#tsdb_league').val();
        if(!league){ return; }
        const res = await fetch(`${apiBase}seasons?league=${encodeURIComponent(league)}`);
        const data = await res.json();
        const select = $('#tsdb_season').empty();
        select.append('<option value="">Select Season</option>');
        data.forEach(s => {
            const name = s.strSeason || s.name;
            select.append(`<option value="${name}">${name}</option>`);
        });
    }

    $(function(){
        populateCountries();
        populateSports();
        $('#tsdb_country, #tsdb_sport').on('change', populateLeagues);
        $('#tsdb_league').on('change', populateSeasons);
        $('#tsdb_sync_btn').on('click', function(e){
            e.preventDefault();
            const country = $('#tsdb_country').val();
            const sport = $('#tsdb_sport').val();
            $.post(ajaxurl, {
                action: 'tsdb_sync_leagues',
                country: country,
                sport: sport,
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
    });
})(jQuery);
