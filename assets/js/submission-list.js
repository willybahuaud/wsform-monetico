(function wsformMoneticoSubmissionListInit( windowObject, documentObject ) {
    var settings = windowObject.wsformMoneticoSubmissionList || {};

    if ( ! settings.columns ) {
        return;
    }

    function onReady( callback ) {
        if ( 'loading' !== documentObject.readyState ) {
            callback();
            return;
        }

        documentObject.addEventListener( 'DOMContentLoaded', callback, false );
    }

    function normalizeText( value ) {
        if ( ! value ) {
            return '';
        }

        return value.toString().trim();
    }

    function createBadge( statusValue ) {
        var labels = settings.statusLabels || {};
        var fallback = settings.labels ? settings.labels.na : '';
        var status = normalizeText( statusValue ).toLowerCase();
        var badge = documentObject.createElement( 'span' );
        var slug = status && labels[ status ] ? status : 'na';

        badge.className = 'wsf-monetico-badge wsf-monetico-status-' + slug;
        badge.textContent = labels[ status ] || fallback;

        return badge;
    }

    function renderStatusBadges() {
        var columnName = settings.columns.status;
        if ( ! columnName ) {
            return;
        }

        var cells = documentObject.querySelectorAll( 'td.column-' + columnName );

        for ( var index = 0; index < cells.length; index++ ) {
            var cell = cells[ index ];
            var badge = createBadge( cell.textContent );

            cell.textContent = '';
            cell.appendChild( badge );
        }
    }

    function renderReferences() {
        var columnName = settings.columns.reference;
        if ( ! columnName ) {
            return;
        }

        var cells = documentObject.querySelectorAll( 'td.column-' + columnName );
        var fallback = settings.labels ? settings.labels.na : '';

        for ( var index = 0; index < cells.length; index++ ) {
            var cell = cells[ index ];
            var textValue = normalizeText( cell.textContent );
            var hasValue = '' !== textValue;

            var code = documentObject.createElement( 'code' );
            code.className = 'wsf-monetico-reference';
            code.textContent = hasValue ? textValue : fallback;

            cell.textContent = '';
            cell.appendChild( code );
        }
    }

    function renderAll() {
        renderStatusBadges();
        renderReferences();
    }

    onReady( renderAll );
})( window, document );
