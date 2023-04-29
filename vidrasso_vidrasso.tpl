{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Vidrasso implementation : © Ori Avtalion <ori@avtalion.name>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    vidrasso_vidrasso.tpl

    This is the HTML template of your game.

-->

<div id="vid_player_{TOP_PLAYER_ID}_strawmen_wrap" class="whiteblock">
    <h3>Opponent's strawmen</h3>
    <div>
        <div class="vid_straw" id="vid_playerstraw_{TOP_PLAYER_ID}_1"></div>
        <div class="vid_straw" id="vid_playerstraw_{TOP_PLAYER_ID}_2"></div>
        <div class="vid_straw" id="vid_playerstraw_{TOP_PLAYER_ID}_3"></div>
        <div class="vid_straw" id="vid_playerstraw_{TOP_PLAYER_ID}_4"></div>
        <div class="vid_straw" id="vid_playerstraw_{TOP_PLAYER_ID}_5"></div>
    </div>
</div>

<div id="vid_centerarea">

<!-- BEGIN player -->
<div id="vid_playertable_{PLAYER_ID}" class="vid_playertable whiteblock">
    <div class="vid_playertablename" style="color:#{PLAYER_COLOR}">
        {PLAYER_NAME}
    </div>
    <div class="vid_playertablecard" id="vid_playertablecard_{PLAYER_ID}"></div>
    <span class="vid_playertable_info">
        <span>{SCORE_PILE}: </span>
        <span id="vid_score_pile_{PLAYER_ID}"></span>
    </span>
</div>
<!-- END player -->

<div id="vid_trumpSelector" class="whiteblock">
    <div>
    <div>{TRUMP_RANK}:</div>
    <div id="vid_trump_rank" class="vid_trump_indicator"></div>
    <ul id="vid_rankSelector">
        <li data-type="rank" data-id="1">1</li>
        <li data-type="rank" data-id="2">2</li>
        <li data-type="rank" data-id="3">3</li>
        <li data-type="rank" data-id="4">4</li>
        <li data-type="rank" data-id="5">5</li>
        <li data-type="rank" data-id="6">6</li>
        <li data-type="rank" data-id="7">7</li>
        <li data-type="rank" data-id="8">8</li>
        <li data-type="rank" data-id="9">9</li>
    </ul>
    </div>
    <br>
    <div>
    <div>{TRUMP_SUIT}:</div>
    <div id="vid_trump_suit" class="vid_trump_indicator"></div>
    <ul id="vid_suitSelector">
        <li data-type="suit" class="vid_suit_icon_1" data-id="1"></li>
        <li data-type="suit" class="vid_suit_icon_2" data-id="2"></li>
        <li data-type="suit" class="vid_suit_icon_3" data-id="3"></li>
        <li data-type="suit" class="vid_suit_icon_4" data-id="4"></li>
    </ul>
    </div>
</div>

</div>

<div id="vid_player_{BOTTOM_PLAYER_ID}_strawmen_wrap" class="whiteblock">
    <h3>{MY_STRAWMEN}</h3>
    <div id="vid_mystrawmen">
        <div class="vid_straw" id="vid_playerstraw_{BOTTOM_PLAYER_ID}_1"></div>
        <div class="vid_straw" id="vid_playerstraw_{BOTTOM_PLAYER_ID}_2"></div>
        <div class="vid_straw" id="vid_playerstraw_{BOTTOM_PLAYER_ID}_3"></div>
        <div class="vid_straw" id="vid_playerstraw_{BOTTOM_PLAYER_ID}_4"></div>
        <div class="vid_straw" id="vid_playerstraw_{BOTTOM_PLAYER_ID}_5"></div>
    </div>
</div>
<div id="vid_myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="vid_myhand">
    </div>
</div>


<script type="text/javascript">

// Javascript HTML templates

var jstpl_cardontable = '<div class="vid_cardontable" id="vid_cardontable_${player_id}" style="background-position:-${x}% -${y}%"></div>';
var jstpl_strawman = '<div class="vid_strawcard" id="vid_straw_${player_id}_${straw_num}" style="background-position:-${x}% -${y}%"></div>';
var jstpl_player_hand_size = '\<div class="vid_hand_size">\
    \<span id="vid_player_hand_size_${id}">0\</span>\
    \<span class="fa fa-hand-paper-o"/>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
