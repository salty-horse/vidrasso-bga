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
            this.cardWidth = 93;
            this.cardHeight = 93;

            this.suitSymbolToId = {
                '♠': 1,
                '♥': 2,
                '♣': 3,
                '♦': 4,
            };
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


        setup: function(gamedatas) {
            console.log('gamedatas', gamedatas);

            this.suitNames = {
                1: _('spades'),
                2: _('hearts'),
                3: _('clubs'),
                4: _('diamonds'),
            };

            // Set dynamic UI strings
            if (this.isSpectator) {
                for (const player_info of Object.values(this.gamedatas.players)) {
                    this.setStrawmanPlayerLabel(player_info);
                }
            } else {
                this.setStrawmanPlayerLabel(gamedatas.players[gamedatas.opponent_id]);
            }

            // Player hand
            this.playerHand = new ebg.stock();
            this.playerHand.setSelectionMode(1);
            this.playerHand.centerItems = true;
            this.playerHand.create(this, $('vid_myhand'), this.cardWidth, this.cardHeight);
            this.playerHand.image_items_per_row = 9;
            this.playerHand.onItemCreate = dojo.hitch(this, this.setupNewCard);
            this.playerHand.jstpl_stock_item = '<div id="${id}" class="vid_card"></div>';

            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            dojo.query('#vid_trumpSelector li').forEach((node, index, arr) => {
                dojo.connect(node, 'onclick', this, 'onChoosingTrump');
            });

            // Create cards types
            for (let suit = 1; suit <= 4; suit++) {
                for (let rank = 1; rank <= 9; rank++) {
                    // Build card type id
                    let card_type_id = this.getCardUniqueId(suit, rank);
                    this.playerHand.addItemType(card_type_id, card_type_id);
                }
            }

            // Used for changing trump graphics
            this.visibleCards = {};

            // Cards in player's hand
            this.initPlayerHand(this.gamedatas.hand);

            // Mapping between strawmen card IDs and elements
            this.strawmenById = {};

            this.scorePiles = {};
            this.handSizes = {};

            for (const [player_id, player_info] of Object.entries(this.gamedatas.players)) {
                // Score piles
                let score_pile_counter = new ebg.counter();
                this.scorePiles[player_id] = score_pile_counter;
                score_pile_counter.create(`vid_score_pile_${player_id}`);
                score_pile_counter.setValue(player_info.score_pile);

                // Hand size counter
                dojo.place(this.format_block('jstpl_player_hand_size', player_info),
                    document.getElementById(`player_board_${player_id}`));
                let hand_size_counter = new ebg.counter();
                this.handSizes[player_id] = hand_size_counter;
                hand_size_counter.create(`vid_player_hand_size_${player_id}`);
                hand_size_counter.setValue(player_info.hand_size);

                // Strawmen
                this.initStrawmen(player_id, player_info.visible_strawmen, player_info.more_strawmen);
            }
            this.addTooltipToClass('vid_hand_size', _('Number of cards in hand'), '');

            // Cards played on table
            for (i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.putCardOnTable(player_id, color, value, card.id);
            }

            if (this.gamedatas.trumpRank != '0' || this.gamedatas.trumpSuit != '0') {
                this.markTrumps();
            }

            let elem = document.getElementById('vid_trump_rank');
            if (this.gamedatas.trumpRank != '0') {
                elem.textContent = this.gamedatas.trumpRank;
            } else {
                elem.textContent = '?';
            }

            elem = document.getElementById('vid_trump_suit');
            if (this.gamedatas.trumpSuit != '0') {
                elem.className = `vid_trump_indicator vid_suit_icon_${this.gamedatas.trumpSuit}`;
                elem.title = elem['aria-label'] = this.suitNames[this.gamedatas.trumpSuit];
            } else {
                elem.textContent = '?';
                elem.removeAttribute('title');
                elem.className = 'vid_trump_indicator';
            }

            this.addTooltipToClass('vid_playertablecard', _('Card played on the table'), '');

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
            console.log('Entering state:', stateName);

            switch (stateName) {
            case 'selectTrump':
                if (this.isCurrentPlayerActive()) {
                    document.querySelectorAll('.vid_playertable').forEach(e => e.style.display = 'none');
                    document.getElementById('vid_rankSelector').style.display = (this.gamedatas.trumpRank == '0') ? 'inline-block' : 'none';
                    document.getElementById('vid_suitSelector').style.display = (this.gamedatas.trumpSuit == '0') ? 'inline-block' : 'none';
                }
                let elem = document.getElementById('vid_trump_rank');
                if (this.gamedatas.trumpRank == '0') {
                    elem.textContent = this.isCurrentPlayerActive() ? '' : '?';
                }
                elem = document.getElementById('vid_trump_suit');
                if (this.gamedatas.trumpSuit == '0') {
                    elem.textContent = this.isCurrentPlayerActive() ? '' : '?';
                    elem.removeAttribute('title');
                }
                break;

            // Mark playable cards
            case 'playerTurn':
                this.markActivePlayerTable(true);

                if (!this.isCurrentPlayerActive())
                    break;

                // Highlight playable cards
                for (let card_id of args.args._private.playable_cards) {
                    let elem = document.getElementById(`vid_myhand_item_${card_id}`);
                    // Look for strawman
                    if (!elem) {
                        elem = document.querySelector(`#vid_mystrawmen div[data-card_id="${card_id}"]`)
                    }
                    if (elem) {
                        elem.classList.add('vid_playable');
                    }
                }
                break;

            case 'endHand':
                this.markActivePlayerTable(false);
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName)
        {
            switch (stateName) {
            case 'selectTrump':
                document.getElementById('vid_rankSelector').style.display = 'none';
                document.getElementById('vid_suitSelector').style.display = 'none';
                document.querySelectorAll('.vid_playertable').forEach(e => e.style.display = '');
                break;
            }
        },

        // onUpdateActionButtons: in this method you can manage 'action buttons' that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args)
        {
            if (this.isCurrentPlayerActive()) {
                switch(stateName) {
                // Mark hand cards if player hasn't gifted yet
                case 'giftCard':
                    document.querySelectorAll('#vid_myhand .vid_card').forEach(
                        e => e.classList.add('vid_playable'));
                    break;
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

        populateCardElement: function(card_div, suit, rank) {
            dojo.place('<div class="vid_card_crown">&nbsp</div>', card_div);
            dojo.place(`<div class="vid_card_main"><div class="vid_card_rank vid_suit_color_${suit}">${rank}</div><div class="vid_card_suit vid_card_suit_${suit}">&nbsp;</div></div>`, card_div);

            if (rank == this.gamedatas.trumpRank) {
                card_div.classList.add('vid_card_trump_rank');
            } else if (suit == this.gamedatas.trumpSuit) {
                card_div.classList.add('vid_card_trump_suit');
            }
        },

        setupNewCard: function(card_div, card_type_id, card_id) {
            let [suit, rank] = this.getCardInfoById(card_type_id);
            this.populateCardElement(card_div, suit, rank);
        },

        /** Override this function to inject html for log items  */

        /* @Override */
        format_string_recursive: function (log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;

                    for (let key in args) {
                        if (args[key] && typeof args[key] == 'string' && key == 'suit') {
                            args[key] = this.getSuitDiv(args[key]);
                        }
                    }
                }
            } catch (e) {
                console.error(log, args, "Exception thrown", e.stack);
            }
            return this.inherited(this.format_string_recursive, arguments);
        },

        getSuitDiv: function (suit_symbol) {
            let suit_id = this.suitSymbolToId[suit_symbol];
            let suit_name = this.suitNames[suit_id];
            return `<div role=\"img\" title=\"${suit_name}\" aria-label=\"${suit_name}\" class=\"vid_log_suit vid_suit_icon_${suit_id}\"></div>`;
        },

        getCardUniqueId: function(suit, rank) {
            return (suit - 1) * 9 + (rank - 1);
        },

        getCardInfoById: function(card_id) {
            return [
                Math.floor(card_id / 9) + 1,
                card_id % 9 + 1,
            ];
        },

        initPlayerHand: function(card_list) {
            for (let i in card_list) {
                let card = card_list[i];
                let suit = card.type;
                let rank = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(suit, rank), card.id);
                this.visibleCards[`${suit},${rank}`] = this.playerHand.getItemDivId(card.id);
            }
        },

        initStrawmen: function(player_id, visible_strawmen, more_strawmen) {
            for (const [ix, straw] of visible_strawmen.entries()) {
                if (!more_strawmen || more_strawmen[ix]) {
                    let more = document.createElement('div');
                    more.className = 'vid_straw_more';
                    document.getElementById(`vid_playerstraw_${player_id}_${ix+1}`).prepend(more);
                }
                if (straw) {
                    this.setStrawman(player_id, ix + 1, straw.type, straw.type_arg, straw.id);
                    this.visibleCards[`${straw.type},${straw.type_arg}`] = `vid_straw_${player_id}_${ix + 1}`;
                }
            }
        },

        setStrawman: function(player_id, straw_num, suit, rank, card_id) {
            let elem = document.getElementById(`vid_playerstraw_${player_id}_${straw_num}`);
            let cardElem = dojo.create('div', {
                id: `vid_straw_${player_id}_${straw_num}`,
                class: 'vid_card',
            }, elem);
            this.populateCardElement(cardElem, suit, rank);
            cardElem.dataset.card_id = card_id;
            this.strawmenById[card_id] = cardElem;
            if (player_id == this.player_id) {
                dojo.connect(cardElem, 'onclick', this, 'onChoosingStrawman');
            }
            return cardElem;
        },

        putCardOnTable: function(player_id, suit, rank, card_id) {
            let cardInHand = false;
            let placedCard = dojo.create('div', {
                id: 'vid_cardontable_' + player_id,
                class: 'vid_card vid_cardontable',
            }, 'vid_playertablecard_' + player_id);
            this.populateCardElement(placedCard, suit, rank);
            placedCard.dataset.card_id = card_id;
        },

        playCardOnTable: function(player_id, suit, rank, card_id) {
            this.putCardOnTable(player_id, suit, rank, card_id);

            let strawElem = this.strawmenById[card_id];
            if (strawElem) {
                this.placeOnObject('vid_cardontable_' + player_id, strawElem.id);
                strawElem.remove();
                delete this.strawmenById[card_id];
            } else {
                if (player_id != this.player_id) {
                    // Some opponent played a card
                    // Move card from player panel
                    this.placeOnObject('vid_cardontable_' + player_id, 'overall_player_board_' + player_id);
                } else {
                    // You played a card. If it exists in your hand, move card from there and remove
                    // corresponding item
                    if ($('vid_myhand_item_' + card_id)) {
                        this.placeOnObject('vid_cardontable_' + player_id, 'vid_myhand_item_' + card_id);
                        this.playerHand.removeFromStockById(card_id);
                    }
                }
                this.handSizes[player_id].incValue(-1);
            }

            // In any case: move it to its final destination
            this.slideToObject('vid_cardontable_' + player_id, 'vid_playertablecard_' + player_id).play();
        },

        markActivePlayerTable: function(turn_on, player_id) {
            if (!player_id) {
                player_id = this.getActivePlayerId();
            }
            if (turn_on && player_id && document.getElementById(`vid_playertable_${player_id}`).classList.contains('vid_table_currentplayer'))
                // Do nothing
                return;

            // Remove from all players before adding for desired player
            document.querySelectorAll('#vid_centerarea .vid_table_currentplayer').forEach(
                e => e.classList.remove('vid_table_currentplayer'));
            if (!turn_on) {
                return;
            }
            if (!player_id) {
                return;
            }
            document.getElementById(`vid_playertable_${player_id}`).classList.add('vid_table_currentplayer')
        },

        unmarkPlayableCards: function() {
            document.querySelectorAll('#vid_mystrawmen .vid_playable, #vid_myhand .vid_playable').forEach(
                e => e.classList.remove('vid_playable'));
        },

        setStrawmanPlayerLabel: function(player_info) {
            document.querySelector(`#vid_player_${player_info.id}_strawmen_wrap > h3`).innerHTML = dojo.string.substitute(
                _("${player_name}'s strawmen"),
                {player_name: `<span style="color:#${player_info.color}">${player_info.name}</span>`});
        },

        // Change the graphics of the trump cards and reorder player hand
        markTrumps: function() {
            for (let [key, div_id] of Object.entries(this.visibleCards)) {
                let [suit, rank] = key.split(',');
                let elem = document.getElementById(div_id);
                if (rank == this.gamedatas.trumpRank) {
                    elem.classList.add('vid_card_trump_rank');
                    elem.classList.remove('vid_card_trump_suit');
                } else {
                    elem.classList.remove('vid_card_trump_rank');
                    if (suit == this.gamedatas.trumpSuit) {
                        elem.classList.add('vid_card_trump_suit');
                    } else {
                        elem.classList.remove('vid_card_trump_suit');
                    }
                }
            }

            let weights = {}
            for (let suit = 1; suit <= 4; suit++) {
                for (let rank = 1; rank <= 9; rank++) {
                    // Build card type id
                    let card_type_id = this.getCardUniqueId(suit, rank);

                    if (rank == this.gamedatas.trumpRank) {
                        weights[card_type_id] = -1000 + card_type_id;
                    } else if (suit == this.gamedatas.trumpSuit) {
                        weights[card_type_id] = -100 + card_type_id;
                    } else {
                        weights[card_type_id] = card_type_id;
                    }
                }
            }
            this.playerHand.changeItemsWeight(weights);
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
            if (items.length == 0)
                return
            this.playerHand.unselectAll();
            if (!document.getElementById(this.playerHand.getItemDivId(items[0].id)).classList.contains('vid_playable')) {
                return;
            }

            if (this.checkAction('playCard', true)) {
                var card_id = items[0].id;
                this.ajaxAction('playCard', {
                    id: card_id,
                });
            } else if (this.checkAction('giftCard')) {
                var card_id = items[0].id;
                this.ajaxAction('giftCard', {
                    id: card_id,
                });
            } else {
                this.playerHand.unselectAll();
            }
        },

        onChoosingStrawman: function(event) {
            if (!this.checkAction('playCard', true))
                return;

            if (!event.currentTarget.classList.contains('vid_playable'))
                return;

            let card_id = event.currentTarget.dataset.card_id;
            if (!card_id)
                return;

            this.ajaxAction('playCard', {
                id: card_id,
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
            dojo.subscribe('newHandPublic', this, 'notif_newHandPublic');
            dojo.subscribe('selectTrumpRank', this, 'notif_selectTrumpRank');
            dojo.subscribe('selectTrumpSuit', this, 'notif_selectTrumpSuit');
            dojo.subscribe('giftCardPrivate', this, 'notif_giftCardPrivate');
            dojo.subscribe('giftCard', this, 'notif_giftCard');
            dojo.subscribe('playCard', this, 'notif_playCard');
            this.notifqueue.setSynchronous('playCard', 1000);
            dojo.subscribe('revealStrawmen', this, 'notif_revealStrawmen');
            dojo.subscribe('trickWin', this, 'notif_trickWin');
            dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer');
            this.notifqueue.setSynchronous('giveAllCardsToPlayer', 1000);
            dojo.subscribe('endHand', this, 'notif_endHand');
            dojo.subscribe('newScores', this, 'notif_newScores');
        },

        notif_newHandPublic: function(notif) {
            document.getElementById('vid_trump_rank').textContent = '?';
            let elem = document.getElementById('vid_trump_suit');
            elem.textContent = '?';
            elem.removeAttribute('title');
            elem.className = 'vid_trump_indicator';
            this.gamedatas.trumpRank = '0';
            this.gamedatas.trumpSuit = '0';

            // The spectator doesn't get the private newHand notification
            if (this.isSpectator) {
                this.visibleCards = {};
            }

            // Reset sorting order
            this.markTrumps();

            // Reset scores and hand size
            for (let scorePile of Object.values(this.scorePiles)) {
                scorePile.setValue(0);
            }

            for (let handSize of Object.values(this.handSizes)) {
                handSize.setValue(notif.args.hand_size);
            }

            for (let player_id in notif.args.strawmen) {
                this.initStrawmen(player_id, notif.args.strawmen[player_id]);
            }
        },

        notif_newHand: function(notif) {
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

            this.visibleCards = {};
            this.initPlayerHand(notif.args.hand_cards);
        },

        notif_selectTrumpRank: function(notif) {
            this.gamedatas.trumpRank = notif.args.rank;
            let elem = document.getElementById('vid_trump_rank');
            elem.textContent = notif.args.rank;
            elem.style.display = 'block';
            document.getElementById('vid_rankSelector').style.display = 'none';

            elem = document.getElementById('vid_trump_suit');
            if (elem.style.display == 'none') {
                elem.textContent = '?';
                elem.removeAttribute('title');
                elem.style.display = 'block';
            }

            this.markTrumps();
        },

        notif_selectTrumpSuit: function(notif) {
            this.gamedatas.trumpSuit = notif.args.suit_id;
            let elem = document.getElementById('vid_trump_suit');
            elem.style.display = 'block';
            elem.textContent = '';
            elem.className = `vid_trump_indicator vid_suit_icon_${this.gamedatas.trumpSuit}`;
            elem.title = elem['aria-label'] = this.suitNames[this.gamedatas.trumpSuit];
            document.getElementById('vid_suitSelector').style.display = 'none';

            elem = document.getElementById('vid_trump_rank');
            if (elem.style.display == 'none') {
                elem.style.display = 'block';
            }

            this.markTrumps();
        },

        notif_giftCardPrivate: function(notif) {
            this.unmarkPlayableCards();

            this.playerHand.removeFromStockById(notif.args.card);

            // Hand size is decreased in notif_giftCard
        },

        notif_giftCard: function(notif) {
            this.handSizes[notif.args.player_id].incValue(-1);
        },

        notif_playCard: function(notif) {
            // Mark the active player, in case this was an automated move (skipping playerTurn state)
            this.markActivePlayerTable(true, notif.args.player_id);
            this.unmarkPlayableCards();
            this.playCardOnTable(notif.args.player_id, notif.args.suit_id, notif.args.value, notif.args.card_id);
        },

        notif_revealStrawmen: function(notif) {
            for (let [player_id, revealed_card] of Object.entries(notif.args.revealed_cards)) {
                let pile_id = revealed_card.pile;
                let card = revealed_card.card;

                let pileElem = document.getElementById(`vid_playerstraw_${player_id}_${pile_id}`);
                let more = pileElem.querySelector('.vid_straw_more');
                if (more) {
                    this.fadeOutAndDestroy(more);
                }
                let newCard = this.setStrawman(player_id, pile_id, card.type, card.type_arg, card.id);
                newCard.style.opacity = 0;
                dojo.fadeIn({node: newCard}).play();
            }
        },

        notif_trickWin: function(notif) {
            // We do nothing here (just wait in order players can view the cards played before they're gone
        },

        notif_giveAllCardsToPlayer: function(notif) {
            // Move all cards on table to given table, then destroy them
            let winner_id = notif.args.player_id;
            for (let player_id in this.gamedatas.players) {
                // Make sure the moved card is above the winner card
                let animated_id = 'vid_cardontable_' + player_id;
                if (player_id != winner_id) {
                    document.getElementById(animated_id).style.zIndex = 3;
                }

                let anim = this.slideToObject(animated_id, 'vid_cardontable_' + winner_id);
                dojo.connect(anim, 'onEnd', (node) => {
                    dojo.destroy(node);
                });
                anim.play();
            }
            this.scorePiles[winner_id].incValue(notif.args.points);
        },

        notif_endHand: function(notif) {
            this.scorePiles[notif.args.player_id].incValue(notif.args.gift_value);
        },

        notif_newScores: function(notif) {
            // Update players' scores
            for (let player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
   });
});
