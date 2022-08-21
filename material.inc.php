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
 * material.inc.php
 *
 * game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->suits = [
    1 => [ 'name' => clienttranslate('spades'),
           'nametr' => self::_('spades') ],
    2 => [ 'name' => clienttranslate('hearts'),
           'nametr' => self::_('hearts') ],
    3 => [ 'name' => clienttranslate('clubs'),
           'nametr' => self::_('clubs') ],
    4 => [ 'name' => clienttranslate('diamonds'),
           'nametr' => self::_('diamonds') ]
];

$this->values_label = [
    1 => '1',
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
];
