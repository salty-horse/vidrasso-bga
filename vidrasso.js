/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Vidrasso implementation : © Ori Avtalion <ori@avtalion.name>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * User interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

'use strict';

define([
    'dojo',
    'dojo/_base/declare',
    'dojo/dom',
    'dojo/on',
    'ebg/core/gamegui',
    'ebg/counter',
    'ebg/stock'
],
function (dojo, declare) {
    return declare('bgagame.vidrasso', ebg.core.gamegui, {
        constructor: function(){
            console.log('vidrasso constructor');

            this.cardWidth = 72;
            this.cardHeight = 96;

            this.suitSymbols = {
                1: {text: '♠', color: 'black'},
                2: {text: '♥', color: 'red'},
                3: {text: '♣', color: 'black'},
                4: {text: '♦', color: 'red'},
            }
        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameters.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)

            'gamedatas' argument contains all datas retrieved by your 'getAllDatas' PHP method.
        */


        setup : function(gamedatas) {
            console.log('Starting game setup');
            console.log('gamedatas', gamedatas);

            // Player hand
            this.playerHand = new ebg.stock();
            this.playerHand.setSelectionMode(1);
            this.playerHand.create(this, $('myhand'), this.cardWidth, this.cardHeight);
            this.playerHand.image_items_per_row = 13;

            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            dojo.query('#trumpSelector li').forEach((node, index, arr) => {
                dojo.connect(node, 'onclick', this, 'onChoosingTrump');
            });

            dojo.query('#mystrawmen .straw').forEach((node, index, arr) => {
                dojo.connect(node, 'onclick', this, 'onChoosingStrawman');
            });

            // Create cards types
            for (let suit = 1; suit <= 4; suit++) {
                for (let rank = 1; rank <= 9; rank++) {
                    // Build card type id
                    let card_type_id = this.getCardUniqueId(suit, rank);
                    this.playerHand.addItemType(card_type_id, suit * 10 + rank, g_gamethemeurl + 'img/cards.jpg', card_type_id);
                }
            }


            // Cards in player's hand
            for (var i in this.gamedatas.hand) {
                var card = this.gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Strawmen
            for (const [player_id, player_info] of Object.entries(this.gamedatas.players)) {
                // FIXME
                if (!document.getElementById(`playerstraw_${player_id}_1`)) continue;

                for (const [ix, straw] of player_info.visible_strawmen.entries()) {
                    if (!straw) continue;
                    this.setStrawman(player_id, ix + 1, straw.type, straw.type_arg, straw.id);
                    if (player_info.more_strawmen[ix]) {
                        document.getElementById(`straw_${player_id}_${ix+1}`).classList.add('straw_more');
                    }
                }
            }

            // Cards played on table
            for (i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }

            if (this.gamedatas.trumpRank != "0") {
                let elem = document.getElementById('trump_rank');
                elem.textContent = this.gamedatas.trumpRank;
            }

            if (this.gamedatas.trumpSuit != "0") {
                let elem = document.getElementById('trump_suit');
                let suit = this.suitSymbols[this.gamedatas.trumpSuit];
                elem.textContent = suit.text;
                elem.style.color = suit.color;
            }

            this.addTooltipToClass("playertablecard", _("Card played on the table"), '');

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
            
            this.ensureSpecificImageLoading(['../common/point.png']);
        },

        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function(stateName, args)
        {
            console.log('Entering state: '+stateName);

            switch (stateName) {
            case 'selectTrump':
                if(this.isCurrentPlayerActive()) {
                    document.getElementById('trumpSelector').style.display = 'block';
                }
                break;

            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName)
        {
            console.log('Leaving state: '+stateName);

            switch (stateName) {
            case 'selectTrump':
                document.getElementById('trumpSelector').style.display = 'none';
                break;

            case 'dummmy':
                break;
            }
        },

        // onUpdateActionButtons: in this method you can manage 'action buttons' that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args)
        {
            console.log('onUpdateActionButtons: '+stateName);

            if(this.isCurrentPlayerActive())
            {
                switch(stateName)
                {
/*
                 Example:

                 case 'myGameState':

                    // Add 3 action buttons in the action status bar:

                    this.addActionButton('button_1_id', _('Button 1 label'), 'onMyMethodToCall1');
                    this.addActionButton('button_2_id', _('Button 2 label'), 'onMyMethodToCall2');
                    this.addActionButton('button_3_id', _('Button 3 label'), 'onMyMethodToCall3');
                    break;
*/
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */

        ajaxAction: function (action, args, func, err, lock) {
            if (!args) {
                args = [];
            }
            delete args.action;
            if (!args.hasOwnProperty('lock') || args.lock) {
                args.lock = true;
            } else {
                delete args.lock;
            }
            if (typeof func == 'undefined' || func == null) {
                func = result => {};
            }

            let name = this.game_name;
            this.ajaxcall(`/vidrasso/vidrasso/${action}.html`, args, this, func, err);
        },

        getCardUniqueId: function(suit, rank) {
            if (rank == 1) {
                rank = 12;
            } else {
                rank -= 2;
            }
            return (suit - 1) * 13 + rank;
        },

        getCardSpriteXY: function(suit, rank) {
            if (rank == 1) {
                rank = 12;
            } else {
                rank -= 2;
            }
            return {
                x: this.cardWidth * rank,
                y: this.cardHeight * (suit - 1),
            }
        },

        setStrawman: function(player_id, straw_num, suit, rank, card_id) {
            let spriteCoords = this.getCardSpriteXY(suit, rank);
            let elem = document.getElementById(`playerstraw_${player_id}_${straw_num}`);
            elem.dataset.id = card_id;
            dojo.place(this.format_block('jstpl_strawman', {
                x: spriteCoords.x,
                y: spriteCoords.y,
                player_id: player_id,
                straw_num: straw_num,
            }), elem);
        },

        playCardOnTable: function(player_id, suit, rank, card_id) {
            let spriteCoords = this.getCardSpriteXY(suit, rank);
            dojo.place(this.format_block('jstpl_cardontable', {
                x : spriteCoords.x,
                y : spriteCoords.y,
                player_id : player_id
            }), 'playertablecard_' + player_id);

            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + player_id, 'overall_player_board_' + player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item

                if ($('myhand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + player_id, 'myhand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + player_id, 'playertablecard_' + player_id).play();
        },

        // /////////////////////////////////////////////////
        // // Player's action

        /*
         *
         * Here, you are defining methods to handle player's action (ex: results of mouse click on game objects).
         *
         * Most of the time, these methods: _ check the action is possible at this game state. _ make a call to the game server
         *
         */

        onPlayerHandSelectionChanged: function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                if (this.checkAction('playCard', true)) {
                    var card_id = items[0].id;
                    this.ajaxAction('playCard', {
                        id: card_id,
                        lock: true
                    });
                } else if (this.checkAction('giftCard')) {
                    var card_id = items[0].id;
                    this.ajaxAction('giftCard', {
                        id: card_id,
                        lock: true
                    });
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        onChoosingStrawman: function(event) {
            if (!this.checkAction('playCard', true))
                return;

            let card_id = event.currentTarget.dataset.id;
            if (!card_id)
                return;

            this.ajaxAction('playCard', {
                id: card_id,
                lock: true
            });
        },

        onChoosingTrump: function(event) {
            if (!this.checkAction('selectTrump'))
                return;

            let data = event.currentTarget.dataset;
            this.ajaxAction('selectTrump', {
                trump_type: data.type,
                id: data.id,
                lock : true
            });
        },

        /*
         * Example:
         *
         * onMyMethodToCall1: function(evt) { console.log('onMyMethodToCall1'); // Preventing default browser reaction dojo.stopEvent(
         * evt); // Check that this action is possible (see 'possibleactions' in states.inc.php) if(! this.checkAction('myAction')) {
         * return; }
         *
         * this.ajaxcall('/heartsla/heartsla/myAction.html', { lock: true, myArgument1: arg1, myArgument2: arg2, ... }, this, function(
         * result) { // What to do after the server call if it succeeded // (most of the time: nothing) }, function(is_error) { // What to
         * do after the server call in anyway (success or failure) // (most of the time: nothing) }); },
         *
         */


        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to 'notifyAllPlayers' and 'notifyPlayer' calls in
                  your template.game.php file.

        */
        setupNotifications: function() {
            console.log('notifications subscriptions setup');

            dojo.subscribe('newHand', this, 'notif_newHand');
            dojo.subscribe('selectTrumpRank', this, 'notif_selectTrumpRank');
            dojo.subscribe('selectTrumpSuit', this, 'notif_selectTrumpSuit');
            dojo.subscribe('giftCard', this, 'notif_giftCard');
            dojo.subscribe('playCard', this, 'notif_playCard');

            dojo.subscribe('trickWin', this, 'notif_trickWin');
            this.notifqueue.setSynchronous('trickWin', 1000);
            dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer');
            dojo.subscribe('newScores', this, 'notif_newScores');
        },

        notif_newHand: function(notif) {
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        notif_selectTrumpRank: function(notif) {
            let elem = document.getElementById('trump_rank');
            elem.textContent = notif.args.rank;
        },

        notif_selectTrumpSuit: function(notif) {
            let elem = document.getElementById('trump_suit');
            let suit = this.suitSymbols[notif.args.suit_id];
            elem.textContent = suit.text;
            elem.style.color = suit.color;
        },

        notif_giftCard: function(notif) {
            this.playerHand.removeFromStockById(notif.args.card);
        },

        notif_playCard: function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
        },


        notif_trickWin: function(notif) {
            // We do nothing here (just wait in order players can view the 4 cards played before they're gone.
        },
        notif_giveAllCardsToPlayer: function(notif) {
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.player_id;
            for (var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + player_id, 'overall_player_board_' + winner_id);
                dojo.connect(anim, 'onEnd', (node) => {
                    dojo.destroy(node);
                });
                anim.play();
            }
        },
        notif_newScores: function(notif) {
            // Update players' scores
            for (var player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
   });
});
