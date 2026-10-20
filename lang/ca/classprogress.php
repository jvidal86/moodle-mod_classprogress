<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Catalan strings for the class progress activity.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addunplanned'] = 'Afegeix una sessió no prevista';
$string['aiinstructions'] = 'Escriu la meva programació anual en Markdown, exactament en el format següent, perquè la pugui importar a Moodle. Respon només amb la programació, sense res més després: tot el que hi hagi després de l\'últim dia es llegiria com a part d\'aquell dia.

# <codi del mòdul> · <títol del mòdul>
(una sola vegada, com a primer encapçalament)

## <RA o bloc> · <títol>
(un encapçalament per a cada resultat d\'aprenentatge o bloc)

### <data> [<hores>h] <títol curt opcional>
<què fem aquell dia, com a llista Markdown; s\'hi admeten negretes i enllaços>

Regles:
- Un encapçalament ### per a cada dia de classe, per ordre de data. S\'admeten dos dies amb la mateixa data.
- Escriu les dates com a 2026-09-16 o 16/09/2026. Davant de la data hi pot anar el dia de la setmana (dl, dt, dc, dj, dv, Mon, Tue, lun, mar, ...).
- Escriu les hores com a [2h] o [1,5h]. Si no les poses, s\'agafen les hores del meu horari setmanal.
- Tot el que hi ha sota un encapçalament ###, fins al següent encapçalament, és la programació d\'aquell dia.
- No afegeixis cap altre encapçalament #. No facis servir encapçalaments #, ## o ### dins del contingut d\'un dia.';
$string['aiinstructionsheading'] = 'Instruccions per al meu assistent d\'IA';
$string['aiinstructionsintro'] = 'Perquè un assistent d\'IA us escrigui o us converteixi la programació, copieu aquestes instruccions al xat juntament amb la vostra programació i, després, enganxeu-ne aquí la resposta.';
$string['allsessions'] = 'Totes les sessions';
$string['approveplan'] = 'Aprova la programació';
$string['approveplanintro'] = 'L\'aprovació és definitiva. Un cop aprovada la programació, ja no se\'n poden canviar els dies, les dates, les hores, el text ni l\'horari, i no es pot suprimir. Comproveu els dies de sota abans d\'aprovar-la.';
$string['approveplannoerrors'] = 'El text de la programació té errors i, per tant, la programació no es pot aprovar. Primer importeu-ne un text corregit.';
$string['block'] = 'RA o bloc';
$string['calendar'] = 'Calendari';
$string['calendar_invalid_date'] = 'El primer o l\'últim dia de classe no és una data vàlida.';
$string['calendar_last_before_first'] = 'L\'últim dia de classe és anterior al primer, de manera que no hi ha sessions.';
$string['calendar_missing_dates'] = 'Introduïu el primer i l\'últim dia de classe per veure el calendari.';
$string['calendar_out_of_range'] = 'El primer o l\'últim dia de classe no és entre el 2000-01-01 i el 2099-12-31, de manera que no hi ha calendari.';
$string['calendar_too_long'] = 'Els dies de classe abasten més de 400 dies, de manera que no hi ha calendari.';
$string['calendarsummary'] = '{$a->sessions}, {$a->hours}';
$string['changetext'] = 'Canvia el text';
$string['chooseschoolyear'] = 'Trieu un curs escolar';
$string['classprogress:addinstance'] = 'Afegeix un Seguiment de classe';
$string['classprogress:edit'] = 'Importa i aprova programacions del Seguiment de classe i registra\'n les sessions';
$string['classprogress:view'] = 'Visualitza les programacions del Seguiment de classe i els seus registres';
$string['date'] = 'Data';
$string['daterange'] = '{$a->start} a {$a->end}';
$string['dates'] = 'Dates';
$string['days'] = 'Dies';
$string['daytitle'] = 'Títol';
$string['deletedentries'] = 'Historial dels registres suprimits';
$string['deleteduser'] = 'Usuari suprimit';
$string['deleteentry'] = 'Suprimeix el registre';
$string['deleteentryconfirm'] = 'Voleu suprimir el registre de la sessió del {$a}? Se\'n conserva l\'historial.';
$string['editentry'] = 'Edita el registre';
$string['entrycarry'] = 'Continua a la sessió següent';
$string['entrycontent'] = 'Què s\'ha fet';
$string['entrydeleted'] = 'S\'ha suprimit el registre. Se\'n conserva l\'historial.';
$string['entryissue_bad_date'] = 'Introduïu una data que existeixi, escrita aaaa-mm-dd, com ara 2026-10-15.';
$string['entryissue_bad_reason'] = 'Trieu un dels motius de la llista.';
$string['entryissue_bad_status'] = 'Trieu un dels estats de la llista.';
$string['entryissue_content_required'] = 'Escriviu què s\'ha fet.';
$string['entryissue_future_not_done_only'] = 'Aquesta sessió és posterior a avui: fins que no arribi el dia, només es pot registrar com a no feta, per a una anul·lació que ja se sap per endavant.';
$string['entryissue_future_unplanned'] = 'Una sessió no prevista no pot tenir una data posterior a avui.';
$string['entryissue_out_of_range'] = 'Introduïu una data entre el 2000-01-01 i el 2099-12-31.';
$string['entryissue_outside_year'] = 'La data ha de ser dins del curs escolar.';
$string['entryissue_reason_required'] = 'Trieu el motiu pel qual la sessió no s\'ha fet del tot.';
$string['entrylastedited'] = 'Última modificació: {$a}.';
$string['entrymeasure'] = 'Mesura correctora';
$string['entryreason'] = 'Motiu';
$string['entryreasonnote'] = 'Nota del motiu';
$string['entrysaved'] = 'S\'ha desat el registre.';
$string['entrystatus_added'] = 'Afegida';
$string['entrystatus_done'] = 'Fet';
$string['entrystatus_not_done'] = 'No fet';
$string['entrystatus_not_logged'] = 'Sense registrar';
$string['entrystatus_partial'] = 'Parcial';
$string['entrystatus_upcoming'] = 'Futura';
$string['entrywrittenon'] = 'Escrit el {$a}.';
$string['error_baddate'] = 'Habiliteu la data i trieu un dia que existeixi.';
$string['error_changedelsewhere'] = 'Això s\'ha canviat en un altre lloc; torneu a carregar la pàgina per veure\'n la versió més recent';
$string['error_confirmwarnings'] = 'Confirmeu que heu llegit els avisos.';
$string['error_dateoutsideyear'] = 'La data ha de ser dins del curs escolar.';
$string['error_daterange'] = 'Introduïu una data entre el 2000-01-01 i el 2099-12-31.';
$string['error_endbeforestart'] = 'L\'últim dia és anterior al primer.';
$string['error_hours'] = 'Introduïu un nombre d\'hores entre 0 i 24.';
$string['error_name'] = 'Introduïu un nom de 255 caràcters com a màxim.';
$string['error_noschoolyear'] = 'Aquesta programació no té curs escolar. Primer trieu un dels vostres cursos escolars a la configuració de l\'activitat.';
$string['error_notfound'] = 'Aquest element no existeix o no teniu permís per veure\'l.';
$string['error_notimported'] = 'Importeu el text de la programació abans d\'aprovar-la.';
$string['error_planhaserrors'] = 'El text de la programació té errors. Corregiu-los; no s\'ha desat res.';
$string['error_planlocked'] = 'Aquesta programació s\'ha aprovat i ja no es pot canviar.';
$string['error_plannotapproved'] = 'Només es poden registrar i exportar les sessions d\'una programació aprovada.';
$string['error_plantoolarge'] = 'El text de la programació ocupa més de {$a} kB, el màxim que pot tenir una programació.';
$string['error_schoolyear'] = 'Trieu un dels vostres cursos escolars.';
$string['error_weekday'] = 'L\'horari té un dia que no és cap dia de la setmana.';
$string['evententrycreated'] = 'Registre creat';
$string['evententrydeleted'] = 'Registre suprimit';
$string['evententryupdated'] = 'Registre actualitzat';
$string['eventplanapproved'] = 'Programació aprovada';
$string['eventplanimported'] = 'Programació importada';
$string['export'] = 'Exporta';
$string['exportcol_block'] = 'Bloc';
$string['exportcol_carry'] = 'Continua a la sessió següent';
$string['exportcol_date'] = 'Data';
$string['exportcol_dayslate'] = 'Dies de retard';
$string['exportcol_done'] = 'Realitzat';
$string['exportcol_hours'] = 'Hores previstes';
$string['exportcol_loggedby'] = 'Registrat per';
$string['exportcol_measure'] = 'Mesura correctora';
$string['exportcol_note'] = 'Nota del motiu';
$string['exportcol_period'] = 'Avaluació';
$string['exportcol_planned'] = 'Previst';
$string['exportcol_reason'] = 'Motiu';
$string['exportcol_status'] = 'Estat';
$string['exportcol_writtenon'] = 'Escrit el';
$string['exportdownload'] = 'Baixa el full de càlcul (XLSX)';
$string['exportfig_added'] = 'Afegida';
$string['exportfig_coverage'] = 'Cobertura (%)';
$string['exportfig_done'] = 'Fet';
$string['exportfig_group'] = 'Agrupació';
$string['exportfig_hoursdone'] = 'Hores impartides';
$string['exportfig_hoursnotdone'] = 'Hores no impartides';
$string['exportfig_mostdayslate'] = 'Màxim de dies de retard';
$string['exportfig_notdone'] = 'No fet';
$string['exportfig_notlogged'] = 'Sense registrar';
$string['exportfig_partial'] = 'Parcial';
$string['exportfig_planned'] = 'Previst';
$string['exportfig_plannedhours'] = 'Hores previstes';
$string['exportfig_upcoming'] = 'Futura';
$string['exportfig_writtenlate'] = 'Registres amb retard';
$string['exportgroup_all'] = 'Totes les sessions';
$string['exportgroup_no_block'] = 'Sense bloc';
$string['exportgroup_outside'] = 'Fora d\'avaluació';
$string['exportgroup_unplanned'] = 'Sessions no previstes';
$string['exportintro'] = 'La programació comparada amb el que s\'ha registrat, fins avui, {$a}. Una sessió encara sense registrar compta com a no coberta, i una de parcial, com a mitja; les sessions futures queden fora de la cobertura. El full de càlcul conté les mateixes xifres i una fila per sessió.';
$string['exportlabel_activity'] = 'Activitat';
$string['exportlabel_course'] = 'Curs';
$string['exportlabel_exportedby'] = 'Exportat per';
$string['exportlabel_exportedon'] = 'Exportat el';
$string['exportlabel_module'] = 'Mòdul';
$string['exportlabel_plandates'] = 'Dates de la programació';
$string['exportlabel_plannedhours'] = 'Hores previstes';
$string['exportlabel_plannedsessions'] = 'Sessions previstes';
$string['exportlabel_schoolyear'] = 'Curs escolar';
$string['exportsheet_plan'] = 'Previst i realitzat';
$string['exportsheet_summary'] = 'Resum';
$string['exporttable_overall'] = 'Global';
$string['exporttable_perblock'] = 'Per bloc';
$string['exporttable_perperiod'] = 'Per avaluació';
$string['firstclassday'] = 'Primer dia de classe';
$string['firstclassday_help'] = 'El primer i l\'últim dia de classe d\'aquest mòdul. El calendari compta les sessions que hi ha entre aquests dos dies, els dies de la setmana de l\'horari, sense els festius del curs escolar. Deixeu-los sense habilitar si encara no els sabeu.';
$string['history'] = 'Historial';
$string['historyaction_create'] = 'Creat';
$string['historyaction_delete'] = 'Suprimit';
$string['historyaction_update'] = 'Editat';
$string['historyby'] = 'Per {$a}';
$string['historyentry'] = 'Historial del registre';
$string['historyintro'] = 'Tots els canvis, del més recent al més antic, amb el registre tal com va quedar després de cada canvi (tal com era abans, en el cas d\'una supressió). L\'historial no es pot modificar i es conserva quan se suprimeix un registre.';
$string['historyloggedinas'] = 'Per {$a->realuser}, que havia entrat com a {$a->user}';
$string['holiday'] = 'Festiu';
$string['hours'] = 'Hores';
$string['importplan'] = 'Importa el text de la programació';
$string['importplanintro'] = 'Enganxeu a sota el text de la programació, o pengeu-lo com a fitxer Markdown (.md) o de text, i després premeu «Previsualitza». No es desa res fins que no premeu «Desa l\'esborrany» després de la previsualització.';
$string['issue_bad_date'] = 'L\'encapçalament del dia «{$a->text}» no té cap data llegible. Escriviu la data com a 2026-09-16 o 16/09/2026, si voleu precedida d\'un dia de la setmana, com ara dl o dt.';
$string['issue_block_too_long'] = 'L\'encapçalament de RA o bloc que hi ha a sobre d\'aquest dia té més de {$a->max} caràcters.';
$string['issue_empty_day'] = 'El dia {$a->date} no té cap text a la programació.';
$string['issue_missing_hours'] = 'El dia {$a->date} no té hores, i l\'horari no en té cap per a aquell dia de la setmana.';
$string['issue_module_too_long'] = 'L\'encapçalament del mòdul és massa llarg: el codi pot tenir fins a 100 caràcters i el títol, fins a 255.';
$string['issue_multiple_modules'] = 'Un segon encapçalament # de mòdul. Una programació conté un sol mòdul: traieu aquest encapçalament o importeu el mòdul per separat.';
$string['issue_no_block'] = 'El dia {$a->date} no té cap encapçalament ## de RA o bloc a sobre.';
$string['issue_no_class_weekday'] = 'El dia {$a->date} cau en un dia de la setmana que no té classe a l\'horari.';
$string['issue_no_days'] = 'La programació no té cap dia. Escriviu cada dia de classe com un encapçalament ### que comenci amb la seva data.';
$string['issue_no_module_heading'] = 'La programació no té cap encapçalament # de mòdul, de manera que el codi i el títol del mòdul d\'aquesta programació es queden com estan.';
$string['issue_not_utf8'] = 'El text no està en UTF-8. Deseu el fitxer en UTF-8 i torneu-ho a provar.';
$string['issue_on_holiday'] = 'El dia {$a->date} és festiu: {$a->holiday}.';
$string['issue_out_of_order'] = 'El dia {$a->date} surt després del {$a->previous} al text, però és anterior.';
$string['issue_out_of_range'] = 'La data {$a->date} no és entre el 2000-01-01 i el 2099-12-31.';
$string['issue_outside_year'] = 'El dia {$a->date} és fora del curs escolar.';
$string['issue_title_too_long'] = 'El títol del dia {$a->date} té més de {$a->max} caràcters.';
$string['issue_too_many_days'] = 'La programació té més de {$a->max} dies, el màxim que pot tenir una programació. Aquest és el primer dia que passa del límit.';
$string['issue_too_many_hours'] = 'El dia {$a->date} té {$a->hours} hores; un dia en pot tenir 24 com a màxim.';
$string['issue_unclosed_fence'] = 'Aquí s\'obre un bloc de codi que no es tanca mai, de manera que pot amagar els dies que hi ha després.';
$string['issue_unwrapped_fence'] = 'La programació era dins d\'un bloc de codi, de manera que s\'ha llegit d\'aquell bloc i s\'ha descartat el text del voltant.';
$string['issue_weekday_mismatch'] = 'El dia de la setmana «{$a->weekday}» no correspon a la data {$a->date}.';
$string['issueline'] = 'Línia {$a->line}: {$a->message}';
$string['lastclassday'] = 'Últim dia de classe';
$string['late'] = 'Amb {$a} dies de retard';
$string['lateone'] = 'Amb 1 dia de retard';
$string['leftover'] = 'Continuació de la sessió anterior';
$string['log'] = 'Registre de sessions';
$string['logged'] = 'Sessions registrades';
$string['logintro'] = 'Registreu què s\'ha fet a cada sessió. Una sessió registrada després del seu dia mostra amb quants dies de retard s\'ha escrit.';
$string['logsession'] = 'Registra';
$string['maxplandays'] = 'Màxim de dies d\'una programació';
$string['maxplandays_desc'] = 'Es rebutja el text d\'una programació que tingui més dies que aquest nombre.';
$string['maxplansize'] = 'Mida màxima del text d\'una programació (kB)';
$string['maxplansize_desc'] = 'Es rebutja el text d\'una programació, enganxat o penjat, que ocupi més d\'aquesta mida, en kilobytes.';
$string['module'] = 'Mòdul';
$string['modulename'] = 'Seguiment de classe';
$string['modulename_help'] = 'Una activitat Seguiment de classe conté la programació anual d\'un mòdul del curs i el registre d\'allò que s\'ha fet a cada sessió, per a la inspecció. Només la veuen els professors del curs. Importeu la programació en Markdown, reviseu-la i aproveu-la; després registreu cada sessió respecte a la programació. Els registres escrits amb retard queden marcats i cada canvi es conserva en un historial.';
$string['modulenameplural'] = 'Seguiments de classe';
$string['month'] = 'Mes';
$string['noblock'] = '(sense RA ni bloc)';
$string['nodeletedentries'] = 'No s\'ha suprimit cap registre d\'aquesta programació.';
$string['noholidays'] = 'Aquest curs escolar no té cap festiu.';
$string['noitems'] = 'Aquesta programació encara no té dies. Importeu-ne el text per afegir-los.';
$string['nologged'] = 'Encara no s\'ha registrat cap sessió.';
$string['nopending'] = 'No hi ha res pendent: totes les sessions fins avui estan registrades.';
$string['noplanactivities'] = 'En aquest curs no hi ha cap Seguiment de classe que pugueu obrir.';
$string['noschoolyear'] = 'Aquesta programació en esborrany no té curs escolar: el curs escolar que feia servir s\'ha suprimit, o l\'activitat s\'ha restaurat d\'una còpia de seguretat. Trieu un dels vostres cursos escolars a la configuració de l\'activitat abans d\'importar o aprovar la programació.';
$string['noschoolyears'] = 'Encara no teniu cap curs escolar. Una programació en necessita un per als festius i les dates: {$a}.';
$string['noupcoming'] = 'No hi ha cap sessió després d\'avui.';
$string['pending'] = 'Sessions pendents';
$string['pendingintro'] = 'Sessions fins avui que encara no tenen cap registre, de la més antiga a la més recent.';
$string['planapproved'] = 'S\'ha aprovat la programació.';
$string['plandays'] = 'Programació i calendari';
$string['plandraftsaved'] = 'S\'ha desat l\'esborrany. Comproveu-ne els dies i després aproveu la programació.';
$string['planfile'] = 'O pengeu un fitxer';
$string['planfile_help'] = 'Un fitxer Markdown (.md) o de text (.txt), desat en UTF-8. Es llegeix en lloc del quadre de text i no es conserva.';
$string['planname'] = '{$a->code} · {$a->title}';
$string['plannedcontent'] = 'Contingut previst';
$string['plannedday'] = 'Previst per a aquesta sessió';
$string['plansettings'] = 'Programació';
$string['plantext'] = 'Text de la programació';
$string['pluginadministration'] = 'Administració del Seguiment de classe';
$string['pluginname'] = 'Seguiment de classe';
$string['preview'] = 'Previsualització del text de la programació';
$string['previewclean'] = 'Cap error ni avís.';
$string['previewerrors'] = 'Errors: la programació no es pot desar fins que no es corregeixin.';
$string['previewplan'] = 'Previsualitza';
$string['previewreplaces'] = 'L\'encapçalament de mòdul del text substitueix el mòdul escrit per a aquesta programació ({$a}).';
$string['previewwarnings'] = 'Avisos: llegiu-los; quan aproveu la programació, se us demanarà que els confirmeu.';
$string['privacy:metadata:classprogress'] = 'Una activitat de seguiment: la programació anual d\'un mòdul, compartida pels professors del seu curs.';
$string['privacy:metadata:classprogress:approvedby'] = 'El professor que va aprovar la programació.';
$string['privacy:metadata:classprogress:timeapproved'] = 'Quan es va aprovar i bloquejar la programació.';
$string['privacy:metadata:classprogress_entry'] = 'El que va passar realment en una sessió, escrit per un professor del curs. El text pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry:carry'] = 'Si el contingut no acabat passa a una sessió posterior.';
$string['privacy:metadata:classprogress_entry:content'] = 'El que s\'ha fet a la sessió; pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry:measure'] = 'La mesura correctora d\'una desviació; pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry:reason'] = 'El motiu indicat per a una desviació respecte a la programació.';
$string['privacy:metadata:classprogress_entry:reasonnote'] = 'Text lliure sobre el motiu; pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry:sessiondate'] = 'El dia que es va fer la sessió.';
$string['privacy:metadata:classprogress_entry:status'] = 'Si el contingut previst s\'ha fet, s\'ha fet en part o no s\'ha fet.';
$string['privacy:metadata:classprogress_entry:timecreated'] = 'Quan es va crear el registre.';
$string['privacy:metadata:classprogress_entry:timemodified'] = 'Quan es va modificar per última vegada el registre.';
$string['privacy:metadata:classprogress_entry:writtenon'] = 'El dia que es va escriure el registre, que mostra si es va escriure amb retard.';
$string['privacy:metadata:classprogress_entry_hist'] = 'L\'historial de tots els canvis d\'un registre, que es conserva després de suprimir-lo, amb qui va fer cada canvi. Les còpies del registre que conté inclouen text que pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry_hist:action'] = 'Si el registre es va crear, actualitzar o suprimir.';
$string['privacy:metadata:classprogress_entry_hist:realuserid'] = 'L\'usuari real, quan el canvi el va fer algú que havia entrat com a aquest usuari.';
$string['privacy:metadata:classprogress_entry_hist:snapshot'] = 'Una còpia de tots els camps del registre en el moment del canvi, inclòs el text que pot esmentar estudiants.';
$string['privacy:metadata:classprogress_entry_hist:timecreated'] = 'Quan es va fer el canvi.';
$string['privacy:metadata:classprogress_entry_hist:userid'] = 'El professor que va fer el canvi.';
$string['privacy:metadata:classprogress_import'] = 'El registre de les importacions de programacions. El text de la programació es desa a la mateixa programació.';
$string['privacy:metadata:classprogress_import:contenthash'] = 'Una empremta (SHA-1) del text importat.';
$string['privacy:metadata:classprogress_import:daycount'] = 'El nombre de dies llegits del text.';
$string['privacy:metadata:classprogress_import:filename'] = 'El nom del fitxer penjat.';
$string['privacy:metadata:classprogress_import:source'] = 'Si el text es va enganxar o penjar.';
$string['privacy:metadata:classprogress_import:timecreated'] = 'Quan es va importar la programació.';
$string['privacy:metadata:classprogress_import:userid'] = 'El professor que va importar el text.';
$string['privacy:path:approval'] = 'Aprovació';
$string['privacy:path:entries'] = 'Registres';
$string['privacy:path:history'] = 'Historial';
$string['privacy:path:imports'] = 'Importacions';
$string['readwarnings'] = 'He llegit els avisos';
$string['reason_incident'] = 'Incidència';
$string['reason_other'] = 'Altres';
$string['reason_school_event'] = 'Activitat de centre';
$string['reason_strike'] = 'Vaga';
$string['reason_teacher_absent'] = 'Absència del professor';
$string['reason_trip'] = 'Sortida';
$string['resetentries'] = 'Suprimeix les sessions registrades i el seu historial (les programacions es mantenen)';
$string['savedraft'] = 'Desa l\'esborrany';
$string['saveentry'] = 'Desa el registre';
$string['schoolyear'] = 'Curs escolar';
$string['schoolyear_help'] = 'Un dels vostres cursos escolars, amb les dates, els festius i les avaluacions que fa servir la programació. Quan s\'aprova la programació, se\'n queda una còpia pròpia, de manera que els canvis posteriors al curs escolar no l\'afecten.';
$string['schoolyearkept'] = 'El curs escolar actual (d\'un altre professor)';
$string['sessiondate'] = 'Data de la sessió';
$string['sessions'] = 'Sessions';
$string['sessionslost'] = 'Sessions perdudes per festius';
$string['sessionslostcolumn'] = 'Sessions perdudes';
$string['sessionspermonth'] = 'Sessions per mes';
$string['settingslocked'] = 'Aquesta programació s\'ha aprovat: ja no se\'n poden canviar el curs escolar, els dies de classe ni l\'horari. Només se\'n poden canviar encara el nom i la descripció.';
$string['status'] = 'Estat';
$string['statusapproved'] = 'Aprovada el {$a}. La programació està bloquejada: ja no se\'n poden canviar els dies, les dates, les hores, el text ni l\'horari.';
$string['statusapprovedshort'] = 'Aprovada';
$string['statusdraft'] = 'Esborrany: la programació encara es pot canviar, tornar a importar o suprimir.';
$string['statusdraftshort'] = 'Esborrany';
$string['summaryhours'] = '{$a} hores de classe';
$string['summaryhoursone'] = '{$a} hora de classe';
$string['summarysessions'] = '{$a} sessions';
$string['summarysessionsone'] = '{$a} sessió';
$string['timetable'] = 'Horari setmanal';
$string['timetableintro'] = 'Hores de classe d\'aquest mòdul cada dia de la setmana; 0 vol dir que aquell dia no hi ha classe.';
$string['unplannedsession'] = 'Sessió no prevista';
$string['upcoming'] = 'Sessions futures';
$string['upcomingintro'] = 'Fins que no arriba el dia, una sessió només es pot registrar com a no feta, per a una anul·lació que ja se sap per endavant.';
$string['weekday'] = 'Dia de la setmana';
$string['writtenon'] = 'Escrit el';
