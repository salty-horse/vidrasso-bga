<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Vidrasso implementation : © Ori Avtalion <ori@avtalion.name>
  *
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');


class Vidrasso extends Table {

    function __construct() {


        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue

        parent::__construct();
        self::initGameStateLabels([
            'roundNumber' => 10,
            'trumpRank' => 11,
            'trumpSuit' => 12,
            'ledSuit' => 13,
            'firstPlayer' => 14,
            'firstPicker' => 15,
            'player1UsedStrawmanPile' => 16,
            'player2UsedStrawmanPile' => 17,
            'targetPoints' => 100,
        ]);

        $this->deck = self::getNew('module.common.deck');
        $this->deck->init('card');
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return 'vidrasso';
    }

    /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $default_colors = ['ff0000', '008000'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes($player['player_name'])."','".addslashes($player['player_avatar'])."')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, ['ff0000', '008000']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values

        self::setGameStateInitialValue('trumpRank', 0);
        self::setGameStateInitialValue('trumpSuit', 0);
        self::setGameStateInitialValue('player1UsedStrawmanPile', 0);
        self::setGameStateInitialValue('player2UsedStrawmanPile', 0);

        // Init game statistics
        // (note: statistics are defined in your stats.inc.php file)

        // Create cards
        $cards = [];
        foreach ($this->suits as $suit_id => $suit) {
            for ($value = 1; $value <= 9; $value++) {
                $cards[] = ['type' => $suit_id, 'type_arg' => $value, 'nbr' => 1];
            }
        }

        $this->deck->createCards($cards, 'deck');

        // Activate first player (which is in general a good idea :))
        $this->activeNextPlayer();

        $player_id = self::getActivePlayerId();
        self::setGameStateInitialValue('firstPlayer', $player_id);
        self::setGameStateInitialValue('firstPicker', $player_id);

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = [ 'players' => [] ];

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = 'SELECT player_id id, player_score score FROM player';
        $result['players'] = self::getCollectionFromDb($sql);

        // Cards in player hand
        $result['hand'] = $this->deck->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->deck->getCardsInLocation('cardsontable');

        $result['roundNumber'] = $this->getGameStateValue('roundNumber');
        $result['firstPlayer'] = $this->getGameStateValue('firstPlayer');
        $result['firstPicker'] = $this->getGameStateValue('firstPicker');
        $result['trumpRank'] = $this->getGameStateValue('trumpRank');
        $result['trumpSuit'] = $this->getGameStateValue('trumpSuit');

        $score_piles = $this->getScorePiles();

        foreach ($result['players'] as &$player) {
            $player_id = $player['id'];
            $strawmen = $this->getPlayerStrawmen($player_id);
            $player['visible_strawmen'] = $strawmen['visible'];
            $player['more_strawmen'] = $strawmen['more'];
            $player['tricks_won'] = $score_piles[$player_id]['tricks_won'];
            $player['score_pile'] = $score_piles[$player_id]['points'];
        }

        return $result;
    }

    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
    function getGameProgression() {
        // TODO: compute and return the game progression

        return 0;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////
    // TODO: Use single sql query
    function getPlayerStrawmen($player_id) {
        $visible_strawmen = [];
        $hidden_strawmen = [];
        for ($i = 1; $i <= 5; $i++) {
            $straw_cards = array_values($this->deck->getCardsInLocation("straw_{$i}_{$player_id}"));
            if (count($straw_cards) >= 1) {
                array_push($visible_strawmen, $straw_cards[0]);
                array_push($hidden_strawmen, count($straw_cards) == 2);
            } else {
                array_push($visible_strawmen, null);
                array_push($hidden_strawmen, false);
            }
        }

        return [
            'visible' => $visible_strawmen,
            'more' => $hidden_strawmen,
        ];
    }

    function getScorePiles() {
        $players = self::loadPlayersBasicInfos();
        $result = [];
        $pile_size_by_player = [];
        foreach ($players as $player_id => $player) {
            $result[$player_id] = ['points' => 0];
            $pile_size_by_player[$player_id] = 0;
        }

        $cards = $this->deck->getCardsInLocation('scorepile');
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            $result[$player_id]['points'] += $card['type_arg'];
            $pile_size_by_player[$player_id] += 1;
        }

        foreach ($players as $player_id => $player) {
            $result[$player_id]['tricks_won'] = $pile_size_by_player[$player_id] / 2;
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    ////////////
    /*
     * Each time a player is doing some game action, one of the methods below is called.
     * (note: each method below must match an input method in template.action.php)
     */
    function selectTrump($trump_type, $trump_id) {
        self::checkAction('selectTrump');
        $player_id = self::getActivePlayerId();
        $first_picker_player = self::getGameStateValue('firstPicker');
        $trump_suit = $this->getGameStateValue('trumpSuit');
        $trump_rank = $this->getGameStateValue('trumpRank');

        // Make sure the second picker is allowed to pick this trump
        if ($first_picker_player != $player_id) {
            if ($trump_rank && $trump_type == 'rank' || $trump_suit && $trump_type == 'suit' ) {
                throw new BgaUserException(self::_('You cannot choose this trump type'));
            }
        }

        $players = self::loadPlayersBasicInfos();
        if ($trump_type == 'rank') {
            self::setGameStateValue('trumpRank', $trump_id);
            self::notifyAllPlayers('selectTrumpRank', clienttranslate('${player_name} selects ${rank} as the trump rank'), [
                'player_id' => $player_id,
                'player_name' => $players[$player_id]['player_name'],
                'rank' => $this->values_label[$trump_id],
            ]);
        } else {
            self::setGameStateValue('trumpSuit', $trump_id);
            self::notifyAllPlayers('selectTrumpSuit', clienttranslate('${player_name} selects ${suit} as the trump suit'), [
                'player_id' => $player_id,
                'player_name' => $players[$player_id]['player_name'],
                'suit' => $this->suits[$trump_id]['name'],
            ]);
        }

        if ($first_picker_player == $player_id) {
            $this->gamestate->nextState('selectOtherTrump');
        } else {
            $this->gamestate->nextState('giftCard');
        }
    }

    function giftCard($card_id) {
        $cards_in_hand = getPlayerHand($player_id);
        if (!in_array($card, array_keys($cards_in_hand))) {
            throw new BgaUserException(self::_('You do not have that card.'));
        }
        $this->deck->moveCard($card_id, 'gift', self::getPlayerAfter($player_id));
        self::notifyPlayer($player_id, 'giftCard', '', ['cards' => $card_id]);
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function playCard($card_id) {
        self::checkAction('playCard');
        $player_id = self::getActivePlayerId();
        $current_card = $this->deck->getCard($card_id);

        // Sanity check. A more thorough check is done later.
        if ($current_card['location_arg'] != $player_id) {
            throw new BgaUserException(self::_('You do not have this card'));
        }

        // Collect all cards in hand and visible strawmen
        $available_cards = getPlayerHand($player_id);
        $strawmen = $this->getPlayerStrawmen($player_id);
        foreach ($strawmen['visible'] as $straw_card) {
            if ($straw_card) {
                $available_cards[$straw_cards['id']] = $straw_card;
            }
        }

        // Check that player has the card in hand or as a visible strawman
        if (!array_key_exists($card_id, $available_cards)) {
            throw new BgaUserException(self::_('You do not have this card'));
        }

        // If this is a followed card, make sure it's in the led suit or a trump suit/rank.
        // If not, make sure the player has no cards of the led suit.
        $led_suit = self::getGameStateValue('ledSuit');
        if (intval($this->deck->countCardInLocation('cardsontable')) > 0) {
            $trump_rank = $this->getGameStateValue('trumpRank');
            $trump_suit = $this->getGameStateValue('trumpSuit');
            if ($current_card['type'] != $led_suit && $current_card['type'] != $trump_suit && $current_card['type_arg'] != $trump_rank) {
                // Verify the player has no cards of the led suit
                foreach ($available_cards as $available_card_id) {
                    $card = $this->deck->getCard($available_card_id);
                    if ($card['type'] == $led_suit)
                        throw new BgaUserException(self::_('You cannot play off-suit'));
                }
            }
        }

        // Remember if the played card is a strawman
        if (str_starts_with($current_card['location'], 'straw')) {
            self.setGameStateValue(
                'player'.getPlayerNoById($player_id).'UsedStrawmanPile',
                $current_card['location'][6]);
        }

        $this->deck->moveCard($card_id, 'cardsontable', $player_id);
        if (self::getGameStateValue('ledSuit') == 0)
            self::setGameStateValue('ledSuit', $current_card['type']);
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}'), [
            'i18n' => ['color_displayed','value_displayed'],
            'card_id' => $card_id,'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $current_card ['type_arg'],
            'value_displayed' => $this->values_label[$current_card['type_arg']],
            'color' => $current_card ['type'],
            'color_displayed' => $this->suits[$current_card['type']]['name']]);
        // Next player
        $this->gamestate->nextState('playCard');
    }

        //////////////////////////////////////////////////////////////////////////////
        //////////// Game state arguments
        ////////////
        /*
     * Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
     * These methods function is to return some additional information that is specific to the current
     * game state.
     */
    function argSelectTrump() {
        $trump_suit = $this->getGameStateValue('trumpSuit');
        $trump_rank = $this->getGameStateValue('trumpRank');
        if (!$trump_suit && !$trump_rank) {
            $rank_or_suit = clienttranslate('rank or suit');
        } else if (!$trump_rank) {
            $rank_or_suit = clienttranslate('rank');
        } else {
            $rank_or_suit = clienttranslate('suit');
        }
        return [
            'i18n' => ['rank_or_suit'],
            'rank_or_suit' => $rank_or_suit,
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    /*
     * Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
     * The action method of state X is called everytime the current game state is set to X.
     */
    function stNewHand() {
        $this->incGameStateValue('roundNumber', 1);

        // Shuffle deck
        $this->deck->moveAllCardsInLocation(null, 'deck');
        $this->deck->shuffle('deck');

        // Deal cards
        $players = self::loadPlayersBasicInfos();
        $public_strawmen = [];
        foreach ($players as $player_id => $player) {
            $hand_cards = $this->deck->pickCards(8, 'deck', $player_id);
            $straw1 = $this->deck->pickCardsForLocation(2, 'deck', "straw_1_{$player_id}");
            $straw2 = $this->deck->pickCardsForLocation(2, 'deck', "straw_2_{$player_id}");
            $straw3 = $this->deck->pickCardsForLocation(2, 'deck', "straw_3_{$player_id}");
            $straw4 = $this->deck->pickCardsForLocation(2, 'deck', "straw_4_{$player_id}");
            $straw5 = $this->deck->pickCardsForLocation(2, 'deck', "straw_5_{$player_id}");

            // TODO: Check that the first card is always first in the response
            $public_strawmen[$player_id] = [
                array_values($straw1)[0],
                array_values($straw2)[0],
                array_values($straw3)[0],
                array_values($straw4)[0],
                array_values($straw5)[0],
            ];

            self::notifyPlayer($player_id, 'newHand', '', ['hand_cards' => $hand_cards]);
        }

        // Notify both players about the public strawmen, first player, and first picker
        self::notifyAllPlayers('newHandPublic', '', [
            'strawmen' => $public_strawmen,
        ]);

        $this->gamestate->nextState('');
    }

	function stMakeNextPlayerActive() {
		$this->activeNextPlayer();
        $this->gamestate->nextState('');
    }

    function stFirstTrick() {
        // New trick: active the player who wins the last trick, or the player who own the club-2 card
        $this->gamestate->changeActivePlayer($this->getGameStateValue('firstPlayer'));
        $this->gamestate->nextState();
    }

    function stNewTrick() {
        // New trick: active the player who wins the last trick, or the player who own the club-2 card
        self::setGameStateValue('ledSuit', 0);
        $this->gamestate->nextState();
    }

    function getCardStrength($card, $trump_suit, $led_suit) {
        $value = -$card['type_arg'];
        if ($card['type'] == $trump_suit) {
            $value -= 100;
        }
        if ($card['type'] == $led_suit) {
            $value -= 50;
        }
        return $value;
    }

    function stNextPlayer() {
        // Move to next player
        if ($this->deck->countCardInLocation('cardsontable') != 2) {
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        // Resolve the trick
        $cards_on_table = $this->deck->getCardsInLocation('cardsontable');
        $winning_player = null;
        $led_suit = self::getGameStateValue('ledSuit');
        $trump_rank = $this->getGameStateValue('trumpRank');
        $trump_suit = $this->getGameStateValue('trumpSuit');

        // Trump rank is involved
        if ($cards_on_table[0]['type_arg'] == $trump_rank || $cards_on_table[1]['type_arg'] == $trump_rank) {
            // If both cards are trump rank, second wins.
            if ($cards_on_table[0]['type_arg'] == $trump_rank && $cards_on_table[1]['type_arg'] == $trump_rank) {
                $winning_player = $cards_on_table[1]['location_arg'];

            // Single trump rank wins.
            } else if ($cards_on_table[0]['type_arg'] == $trump_rank) {
                $winning_player = $cards_on_table[0]['location_arg'];
            } else {
                $winning_player = $cards_on_table[1]['location_arg'];
            }
        } else {
            // Lowest value wins
            $card_0_strength = getCardStrength($cards_on_table[0], $trump_suit, $led_suit);
            $card_1_strength = getCardStrength($cards_on_table[1], $trump_suit, $led_suit);
            if ($card_0_strength < $card_1_strength) {
                $winning_player = $cards_on_table[0]['location_arg'];
            } else {
                $winning_player = $cards_on_table[1]['location_arg'];
            }
        }

        $this->gamestate->changeActivePlayer($winning_player);

        // Move all cards to the winner's scorepile
        $this->deck->moveAllCardsInLocation('cardsontable', 'scorepile', null, $winning_player);

        // Notify
        // Note: we use 2 notifications here in order we can pause the display during the first notification
        //  before we move all cards to the winner (during the second)
        $players = self::loadPlayersBasicInfos();
        self::notifyAllPlayers('trickWin', clienttranslate('${player_name} wins the trick and ${points} points'), [
            'player_id' => $winning_player,
            'player_name' => $players[$winning_player]['player_name'],
            'points' => $cards_on_table[0]['type_arg'] + $cards_on_table[1]['type_arg'],
        ]);
        self::notifyAllPlayers('giveAllCardsToPlayer','', [
            'player_id' => $winning_player,
        ]);

        $this->gamestate->nextState('revealStrawmen');
    }

    function stRevealStrawmen() {
        // Check which piles are revealed and notify players
        $player_strawman_use = [
            1 => self::getGameStateValue('player1UsedStrawmanPile'),
            2 => self::getGameStateValue('player2UsedStrawmanPile')
        ];

        if ($player_strawman_use[1] || $player_strawman_use[2]) {
            $revealed_cards_by_player = [];
            $player_ids_by_no = [];
            $players = self::loadPlayersBasicInfos();
            foreach ($players as $player_id => $player) {
                $player_ids_by_no[$player['player_no']] = $player_id;
                $pile = $player_strawman_use[$player['player_no']];
                if ($pile) {
                    $remaining_cards_in_pile = $this->deck->getCardsInLocation("straw_{$pile}_{$player_id}", null, 'location_arg');
                    if ($remaining_cards_in_pile) {
                        $revealed_cards_by_player[$player_id] = [
                            'pile' => $pile,
                            'new_card' => array_keys($remaining_cards_in_pile)[0],
                        ];
                    }
                }
            }

            self::notifyAllPlayers('revealStrawman', clienttranslate(''), [
                'revealed_cards' => $revealed_cards_by_player,
            ]);

            self::setGameStateValue('player1UsedStrawmanPile', 0);
            self::setGameStateValue('player2UsedStrawmanPile', 0);
        }

        if ($this->deck->countCardInLocation('hand') == 0) {
            // End of the hand
            $this->gamestate->nextState('endHand');
        } else {
            // End of the trick
            $this->gamestate->nextState('nextTrick');
        }
    }

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $score_piles = $this->getScorePiles();

        $gift_cards_by_player = getCollectionFromDB('select card_location_arg id, card_type_arg type_arg from card where card_location = "gift"');

        // Apply scores to player
        foreach ($score_piles as $player_id => $score_pile) {
            $gift_value = $gift_cards_by_player[$player_id]['type_arg'];
            $points = $score_pile['points'] + $gift_value;
            $sql = "UPDATE player SET player_score=player_score+$points  WHERE player_id='$player_id'";
            self::DbQuery($sql);
            self::notifyAllPlayers('points', clienttranslate('${player_name} scores ${points} points (was gifted ${gift_value})'), [
                'player_id' => $player_id,
                'player_name' => $players [$player_id] ['player_name'],
                'points' => $points,
                'gift_value' => $gift_value,
            ]);
        }

        $new_scores = self::getCollectionFromDb('SELECT player_id, player_score FROM player', true);

        // Check if this is the end of the game
        $target_points = $this->getGameStateValue('targetPoints');
        foreach ($new_scores as $player_id => $score) {
            if ($score >= $target_points) {
                // Trigger the end of the game !
                $this->gamestate->nextState('endGame');
                return;
            }
        }

        // Alternate first player
        self::setGameStateValue('firstPlayer', 
            self::getPlayerAfter(self::getGameStateValue('firstPlayer')));

        // Choose new first picker
        $flat_scores = array_values($new_scores);
        if ($flat_scores[0] == $flat_scores[1]) {
            // Rare case when players are tied: Alternate first picker
            self::setGameStateValue('firstPicker', 
                self::getPlayerAfter(self::getGameStateValue('firstPicker')));
        } else {
            // First picker is the player with the lower score
            if ($flat_scores[0] < $flat_scores[1]) {
                $player_with_lowest_score = array_keys($flat_scores)[0];
            } else {
                $player_with_lowest_score = array_keys($flat_scores)[1];
            }
            self::setGameStateValue('firstPicker', $player_with_lowest_score);
        }

        self::notifyAllPlayers('newScores', '', ['newScores' => $new_scores]);

        $this->gamestate->nextState('nextHand');
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] == "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] == "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $sql = "
                UPDATE  player
                SET     player_is_multiactive = 0
                WHERE   player_id = $active_player
            ";
            self::DbQuery($sql);

            $this->gamestate->updateMultiactiveOrNextState('');
            return;
        }

        throw new feException("Zombie mode not supported at this game state: ".$statename);
    }

///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
//        if($from_version <= 1404301345)
//        {
//            $sql = "ALTER TABLE xxxxxxx ....";
//            self::DbQuery($sql);
//        }
//        if($from_version <= 1405061421)
//        {
//            $sql = "CREATE TABLE xxxxxxx ....";
//            self::DbQuery($sql);
//        }
//        // Please add your future database scheme changes here
//
//


    }
}


