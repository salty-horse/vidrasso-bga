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
            'player1UsedStrawman' => 16,
            'player2UsedStrawman' => 17,
            'targetPoints' => 100,
        ]);

        $this->cards = self::getNew('module.common.deck');
        $this->cards->init('card');
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
        foreach($players as $player_id => $player)
        {
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
        self::setGameStateInitialValue('player1UsedStrawman', 0);
        self::setGameStateInitialValue('player2UsedStrawman', 0);

        // Init game statistics
        // (note: statistics are defined in your stats.inc.php file)

        // Create cards
        $cards = [];
        foreach ($this->suits as $suit_id => $suit) {
            for ($value = 1; $value <= 10; $value ++) {
                $cards[] = ['type' => $suit_id, 'type_arg' => $value, 'nbr' => 1];
            }
        }

        $this->cards->createCards($cards, 'deck');

        // Shuffle deck
        $this->cards->shuffle('deck');
        // Deal 13 cards to each players
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $this->cards->pickCards(8, 'deck', $player_id);
            $this->cards->pickCards(8, 'deck', $player_id);
            $this->deck->pickCardsForLocation(2, 'deck', 'straw_'.$player_id.'_1');
            $this->deck->pickCardsForLocation(2, 'deck', 'straw_'.$player_id.'_2');
            $this->deck->pickCardsForLocation(2, 'deck', 'straw_'.$player_id.'_3');
            $this->deck->pickCardsForLocation(2, 'deck', 'straw_'.$player_id.'_4');
            $this->deck->pickCardsForLocation(2, 'deck', 'straw_'.$player_id.'_5');
        }

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
        $sql = 'SELECT player_id id, player_score score FROM player ';
        $result['players'] = self::getCollectionFromDb($sql);

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        $result['roundNumber'] = $this->getGameStateValue('roundNumber');
        $result['firstPlayer'] = $this->getGameStateValue('firstPlayer');
        $result['firstPicker'] = $this->getGameStateValue('firstPicker');
        $result['trumpRank'] = $this->getGameStateValue('trumpRank');
        $result['trumpSuit'] = $this->getGameStateValue('trumpSuit');

        foreach ($result['players'] as &$player) {
            $player_id = $player['id'];
            $visible_strawmen = [];
            $hidden_strawmen = [];
            for ($i = 1; $i <= 5; $i++) {
                $straw_cards = array_values($this->deck->getCardsInLocation('straw_'.$player_id.'_'$i));
                if (count($straw_cards) >= 1) {
                    array_push($visible_strawmen, $straw_cards[0]);
                    array_push($hidden_strawmen, count($straw_cards) == 2);
                } else {
                    array_push($visible_strawmen, null);
                    array_push($hidden_strawmen, false);
                }
            }
            $player['visible_strawmen'] = $visible_strawmen;
            $player['hidden_strawmen'] = $hidden_strawmen;
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
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
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
                throw new BgaUserException(self::_('You Cannot choose this trump type'));
            }
        }

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
            $this->activeNextPlayer();
            $this->gamestate->nextState('selectOtherTrump');
        } else {
            $this->gamestate->nextState('selectGift');
        }
    }

    function giftCard($card_id) {
        $cards_in_hand = getPlayerHand($player_id);
        if (!in_array($card, array_keys($cards_in_hand))) {
            throw new BgaUserException(self::_('You do not have that card.'));
        }
        $this->cards->moveCard($card_id, 'scorepile', self::getPlayerAfter($player_id));
        self::notifyPlayer($player_id, 'giftCard', '', ['cards' => $card_id]);
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function playCard($card_id) {
        self::checkAction('playCard');
        $player_id = self::getActivePlayerId();
        $current_card = $this->cards->getCard($card_id);

        // Sanity check. A more thorough check is done later.
        if ($current_card['location_arg'] != $player_id) {
            throw new BgaUserException(self::_('You do not have this card'));
        }

        // Collect all cards in hand and visible strawmen
        $available_cards = getPlayerHand($player_id);
        for ($i = 1; $i <= 5; $i++) {
            $straw_cards = array_values($this->deck->getCardsInLocation('straw_'.$player_id.'_'.$i));
            if (count($straw_cards) >= 1) {
                $available_cards[$straw_cards[0]['id']] = $straw_cards[0];
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
                    $card = $this->cards->getCard($available_card_id);
                    if ($card['type'] == $led_suit)
                        throw new BgaUserException(self::_('You cannot play off-suit'));
                }
            }
        }

        // Remember if the played card is a strawman
        if (str_starts_with($current_card['location'], 'straw')) {
            self.setGameStateValue(
                'player'.getPlayerNoById($player_id).'UsedStrawman',
                $current_card['location'][5]);
        }

        $this->cards->moveCard($card_id, 'cardsontable', $player_id);
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
    function argGiveCards() {
        return [];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    /*
     * Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
     * The action method of state X is called everytime the current game state is set to X.
     */
    function stNewHand() {
        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        // Deal 13 cards to each players
        // Create deck, shuffle it and give 13 initial cards
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(13, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', ['cards' => $cards]);
        }
        self::setGameStateValue('alreadyPlayedHearts', 0);
        $this->gamestate->nextState("");
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

    function getCardValue($card, $trump_suit, $led_suit) {
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
        if ($this->cards->countCardInLocation('cardsontable') != 2) {
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        // Resolve the trick
        $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
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
            $card_0_value = getCardValue($cards_on_table[0], $trump_suit, $led_suit);
            $card_1_value = getCardValue($cards_on_table[1], $trump_suit, $led_suit);
            if ($card_0_value < $card2Value) {
                $winning_player = $cards_on_table[0]['location_arg'];
            } else {
                $winning_player = $cards_on_table[1]['location_arg'];
            }
        }

        $this->gamestate->changeActivePlayer($winning_player);

        // Move all cards to the winner's scorepile
        $this->cards->moveAllCardsInLocation('cardsontable', 'scorepile', null, $winning_player);

        // TODO: Decide if we want to track points during the hand, and if it should be part of the total score, or a separate score
        // that will only be added to the total at the end of the hand.

        // TODO: Keep count of won tricks?

        // Notify
        // Note: we use 2 notifications here in order we can pause the display during the first notification
        //  before we move all cards to the winner (during the second)
        $players = self::loadPlayersBasicInfos();
        self::notifyAllPlayers('trickWin', clienttranslate('${player_name} wins the trick and ${points} points'), [
            'player_id' => $winning_player,
            'player_name' => $players[$winning_player]['player_name'],
            'points' => 0, // TODO
        ]);
        self::notifyAllPlayers('giveAllCardsToPlayer','', [
            'player_id' => $winning_player,
        ]);

        $this->gamestate->nextState('revealStrawmen');
    }

    function stRevealStrawmen() {
        // TODO check which piles are revealed and notify players
        $player_strawman_use = [
            1 => self::getGameStateValue('player1UsedStrawman'),
            2 => self::getGameStateValue('player2UsedStrawman')
        ];

        if ($player_strawman_use[1] || $player_strawman_use[2]) {
            $player_ids_by_no = [];
            $players = self::loadPlayersBasicInfos();
            foreach ($players as $player_id => $player) {
                $player_ids_by_no[$player['player_no']] = $player_id;
            }

            // TODO Notify players of each use

            self::setGameStateValue('player1UsedStrawman', 0);
            self::setGameStateValue('player2UsedStrawman', 0);
        }

        if ($this->cards->countCardInLocation('hand') == 0) {
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

        $player_to_points = [];
        foreach ($players as $player_id => $player) {
            $player_to_points[$player_id] = 0;
        }
        $cards = $this->cards->getCardsInLocation('scorepile');
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            $player_to_points[$player_id] += $card['type_arg'];
        }

        // TODO: Explicitly reveal the gift card?

        // Apply scores to player
        foreach ($player_to_points as $player_id => $points) {
            if ($points == 0) {
                continue;
            }
            $sql = "UPDATE player SET player_score=player_score+$points  WHERE player_id='$player_id'";
            self::DbQuery($sql);
            self::notifyAllPlayers('points', clienttranslate('${player_name} scores ${points} points'), [
                'player_id' => $player_id,
                'player_name' => $players [$player_id] ['player_name'],
                'points' => $points,
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

        // TODO: Include new first player, new leader, round number
        self::notifyAllPlayers('newScores', '', ['newScores' => $new_scores]);

        // TODO: Increment round number

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


