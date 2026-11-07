(function registerWSFormMoneticoPaymentStatusBlock( blocks, element, i18n, blockEditor, components ) {
    if ( ! blocks || ! element || ! i18n || ! blockEditor || ! components ) {
        return;
    }

    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls || blockEditor.BlockControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;

    blocks.registerBlockType( 'wsform-monetico/payment-status', {
        title: __( 'Statut de paiement Monetico', 'wsform-monetico' ),
        description: __( 'Affiche le statut d’un paiement Monetico à partir de sa référence.', 'wsform-monetico' ),
        icon: 'feedback',
        category: 'widgets',
        attributes: {
            reference: {
                type: 'string',
                default: '',
            },
            showDetails: {
                type: 'boolean',
                default: true,
            },
            messageType: {
                type: 'string',
                default: 'auto',
            },
        },
        edit: function edit( props ) {
            var attrs = props.attributes;

            function updateReference( value ) {
                props.setAttributes( { reference: value } );
            }

            function updateShowDetails( value ) {
                props.setAttributes( { showDetails: value } );
            }

            function updateMessageType( value ) {
                props.setAttributes( { messageType: value } );
            }

            return [
                el(
                    InspectorControls,
                    { key: 'controls' },
                    el(
                        PanelBody,
                        { title: __( 'Affichage', 'wsform-monetico' ), initialOpen: true },
                        el( TextControl, {
                            label: __( 'Référence de paiement', 'wsform-monetico' ),
                            help: __( 'Laisser vide pour afficher le paiement de la personne qui revient de Monetico (URL de retour).', 'wsform-monetico' ),
                            value: attrs.reference,
                            onChange: updateReference,
                        } ),
                        el( ToggleControl, {
                            label: __( 'Afficher les détails', 'wsform-monetico' ),
                            checked: !! attrs.showDetails,
                            onChange: updateShowDetails,
                        } ),
                        el( SelectControl, {
                            label: __( 'Type de message', 'wsform-monetico' ),
                            value: attrs.messageType,
                            options: [
                                { label: __( 'Automatique', 'wsform-monetico' ), value: 'auto' },
                                { label: __( 'Succès', 'wsform-monetico' ), value: 'success' },
                                { label: __( 'Échec', 'wsform-monetico' ), value: 'failure' },
                                { label: __( 'En attente', 'wsform-monetico' ), value: 'pending' },
                            ],
                            onChange: updateMessageType,
                        } )
                    )
                ),
                el(
                    'div',
                    { className: 'wsf-monetico-block-editor' },
                    el(
                        'div',
                        { className: 'wsf-monetico-block-preview' },
                        el(
                            'span',
                            { className: 'wsf-monetico-badge wsf-monetico-status-pending' },
                            __( 'Aperçu', 'wsform-monetico' )
                        ),
                        el(
                            'div',
                            { className: 'wsf-monetico-block-text' },
                            el(
                                'p',
                                { className: 'wsf-monetico-block-title' },
                                __( 'Statut du paiement affiché sur le site.', 'wsform-monetico' )
                            ),
                            el(
                                'p',
                                { className: 'wsf-monetico-block-subtitle' },
                                attrs.reference
                                    ? __( 'Référence : ', 'wsform-monetico' ) + attrs.reference
                                    : __( 'Paiement lu depuis l’URL de retour.', 'wsform-monetico' )
                            )
                        )
                    )
                ),
            ];
        },
        save: function save() {
            return null;
        },
    } );
})( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor || window.wp.editor, window.wp.components );
