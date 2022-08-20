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
 * This is your 'view' file.
 *
 * The method 'build_page' below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * 'build_page' method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in vidrasso_vidrasso.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

require_once(APP_BASE_PATH.'view/common/game.view.php');

class view_vidrasso_vidrasso extends game_view {
    function getGameName() {
        return 'vidrasso';
    }

    function build_page($viewArgs) {
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        /**
         * ********* Place your code below: ***********
         */
        $template = self::getGameName() . '_' . self::getGameName();

        global $g_user;
        $current_player_id = $g_user->get_id();
        
        $this->page->begin_block($template, 'player');
        foreach ($players as $player_id => $info) {
            $dir = ($player_id == $current_player_id) ? 'W': 'E';
            $this->page->insert_block('player', [
                'PLAYER_ID' => $player_id,
                'PLAYER_NAME' => $players[$player_id]['player_name'],
                'PLAYER_COLOR' => $players[$player_id]['player_color'],
                'DIR' => $dir
            ]);
            if ($player_id != $current_player_id) {
                $this->tpl['OP_PLAYER_ID'] = $player_id;
            }
        }
        
        $this->tpl['MY_PLAYER_ID'] = $current_player_id;
        $this->tpl['MY_HAND'] = self::_('My hand');
        $this->tpl['MY_STRAWMEN'] = self::_('My strawmen');
        $this->tpl['OP_STRAWMEN'] = self::_("Opponent's strawmen");
      /*********** Do not change anything below this line  ************/
    }
}
