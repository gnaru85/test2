(function($){
    const apiBase = tsdbAdmin.rest;

    async function apiFetch(path){
        const res = await fetch(apiBase + path, {
            headers: { 'X-WP-Nonce': tsdbAdmin.nonce }
        });
        return res.json();
    }

    async function populateCountries(){
        const data = await apiFetch('countries');
        const select = $('#tsdb_country').empty();
        select.append($('<option>').val('').text('Select Country'));
        data.forEach(c => {
            const name = c.name_en || c.name || c.strCountry;
            const opt = $('<option>').val(name).text(name);
            select.append(opt);
        });
    }

    async function populateSports(){
        const data = await apiFetch('sports');
        const select = $('#tsdb_sport').empty();
        select.append($('<option>').val('').text('Select Sport'));
        data.forEach(s => {
            const name = s.strSport || s.name;
            const opt = $('<option>').val(name).text(name);
            select.append(opt);
        });
    }

    async function populateLeagues(){
        const country = $('#tsdb_country').val();
        const sport = $('#tsdb_sport').val();
        if(!country || !sport){ return; }
        const data = await apiFetch(`leagues?country=${encodeURIComponent(country)}&sport=${encodeURIComponent(sport)}`);
        const select = $('#tsdb_league').empty();
        select.append($('<option>').val('').text('Select League'));
        data.forEach(l => {
            const id = l.idLeague || l.id;
            const name = l.strLeague || l.name;
            const opt = $('<option>').val(id).text(name);
            select.append(opt);
        });
    }

    async function populateSeasons(){
        const league = $('#tsdb_league').val();
        if(!league){ return; }
        const data = await apiFetch(`seasons?league=${encodeURIComponent(league)}`);
        const select = $('#tsdb_season').empty();
        select.append($('<option>').val('').text('Select Season'));
        data.forEach(s => {
            const name = s.strSeason || s.name;
            const opt = $('<option>').val(name).text(name);
            select.append(opt);
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
        $('#tsdb_seed_btn').on('click', function(e){
            e.preventDefault();
            const league = $('#tsdb_league').val();
            const season = $('#tsdb_season').val();
            $.post(ajaxurl, {
                action: 'tsdb_seed',
                league: league,
                season: season,
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
        $('#tsdb_delta_btn').on('click', function(e){
            e.preventDefault();
            const league = $('#tsdb_league').val();
            const season = $('#tsdb_season').val();
            $.post(ajaxurl, {
                action: 'tsdb_delta',
                league: league,
                season: season,
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
        $('#tsdb_clear_cache_btn').on('click', function(e){
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'tsdb_clear_cache',
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
        $('#tsdb_clear_logs_btn').on('click', function(e){
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'tsdb_clear_logs',
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
        $('#tsdb_delete_all_btn').on('click', function(e){
            e.preventDefault();
            if(!confirm('Are you sure?')){ return; }
            $.post(ajaxurl, {
                action: 'tsdb_delete_all_data',
                _ajax_nonce: tsdbAdmin.nonce
            }, function(resp){
                alert(resp.data ? resp.data.message : 'Done');
            });
        });
    });
})(jQuery);
