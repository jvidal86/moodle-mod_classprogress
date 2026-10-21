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
 * Spanish strings for the class progress activity.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addunplanned'] = 'Añadir una sesión no prevista';
$string['aiinstructions'] = 'Escribe mi programación anual en Markdown, exactamente con el formato de abajo, para que pueda importarla en Moodle. Devuelve solo la programación, sin nada después: todo lo que haya después del último día se leería como parte de ese día.

# <código del módulo> · <título del módulo>
(una sola vez, como primer encabezado)

## <RA o bloque> · <título>
(un encabezado para cada resultado de aprendizaje o bloque)

### <fecha> [<horas>h] <título breve opcional>
<lo que hacemos ese día, como lista Markdown; se permiten negritas y enlaces>

Reglas:
- Un encabezado ### para cada día de clase, por orden de fecha. Se permiten dos días con la misma fecha.
- Escribe las fechas como 2026-09-16 o 16/09/2026. Antes de la fecha puede ir el día de la semana (lun, mar, mié, jue, vie, dl, dt, dc, dj, dv, Mon, Tue, ...).
- Escribe las horas como [2h] o [1,5h]. Omítelas para usar las horas de mi horario semanal.
- Todo lo que hay bajo un encabezado ###, hasta el encabezado siguiente, es la programación de ese día.
- No añadas ningún otro encabezado #. No uses encabezados #, ## ni ### dentro de un día.';
$string['aiinstructionsheading'] = 'Instrucciones para mi asistente de IA';
$string['aiinstructionsintro'] = 'Para que un asistente de IA escriba o convierta su programación, copie estas instrucciones en el chat junto con su propia programación y, después, pegue aquí la respuesta del asistente.';
$string['alllogged'] = 'Todas las sesiones registradas ({$a})';
$string['allsessions'] = 'Todas las sesiones';
$string['allupcoming'] = 'Todas las sesiones futuras ({$a})';
$string['approveplan'] = 'Aprobar programación';
$string['approveplanintro'] = 'La aprobación es definitiva. Una vez aprobada la programación, ya no se pueden cambiar sus días, fechas, horas, texto ni horario. Revise los días de abajo antes de aprobarla.';
$string['approveplannoerrors'] = 'El texto de la programación tiene errores, así que la programación no se puede aprobar. Importe antes un texto corregido.';
$string['backtolog'] = 'Volver al registro de sesiones';
$string['block'] = 'RA o bloque';
$string['calendar'] = 'Calendario';
$string['calendar_invalid_date'] = 'El primer o el último día de clase no es una fecha válida.';
$string['calendar_last_before_first'] = 'El último día de clase es anterior al primero, así que no hay sesiones.';
$string['calendar_missing_dates'] = 'Introduzca el primer y el último día de clase para ver el calendario.';
$string['calendar_out_of_range'] = 'El primer o el último día de clase no está entre el 2000-01-01 y el 2099-12-31, así que no hay calendario.';
$string['calendar_too_long'] = 'Los días de clase abarcan más de 400 días, así que no hay calendario.';
$string['calendarsummary'] = '{$a->sessions}, {$a->hours}';
$string['cardnext'] = 'Próxima sesión';
$string['cardtoday'] = 'Hoy';
$string['changetext'] = 'Cambiar el texto';
$string['chooseschoolyear'] = 'Elegir un curso escolar';
$string['classprogress:addinstance'] = 'Añadir una programación de Seguimiento de clase';
$string['classprogress:edit'] = 'Importar y aprobar programaciones de Seguimiento de clase y registrar sus sesiones';
$string['classprogress:view'] = 'Ver las programaciones de Seguimiento de clase y sus registros';
$string['date'] = 'Fecha';
$string['daterange'] = '{$a->start} a {$a->end}';
$string['dates'] = 'Fechas';
$string['days'] = 'Días';
$string['daytitle'] = 'Título';
$string['deletedentries'] = 'Historial de registros eliminados';
$string['deleteduser'] = 'Usuario eliminado';
$string['deleteentry'] = 'Eliminar registro';
$string['deleteentryconfirm'] = '¿Eliminar el registro de la sesión del {$a}? Su historial se conserva.';
$string['editentry'] = 'Editar registro';
$string['entrycarry'] = 'Continúa en la sesión siguiente';
$string['entrycontent'] = 'Qué se ha hecho';
$string['entrydeleted'] = 'Registro eliminado. Su historial se conserva.';
$string['entryissue_bad_date'] = 'Introduzca una fecha que exista, escrita como aaaa-mm-dd, por ejemplo 2026-10-15.';
$string['entryissue_bad_reason'] = 'Elija uno de los motivos de la lista.';
$string['entryissue_bad_status'] = 'Elija uno de los estados de la lista.';
$string['entryissue_content_required'] = 'Escriba lo que se ha realizado.';
$string['entryissue_future_not_done_only'] = 'Esta sesión es posterior a hoy: hasta que llegue su día solo se puede registrar con el estado No hecho, para una anulación que se conoce de antemano.';
$string['entryissue_future_unplanned'] = 'Una sesión no prevista no puede tener una fecha posterior a hoy.';
$string['entryissue_out_of_range'] = 'Introduzca una fecha del 2000-01-01 al 2099-12-31.';
$string['entryissue_outside_year'] = 'La fecha debe estar dentro del curso escolar.';
$string['entryissue_reason_required'] = 'Elija el motivo por el que la sesión no se ha hecho del todo.';
$string['entrylastedited'] = 'Última edición: {$a}.';
$string['entrymeasure'] = 'Medida correctora';
$string['entryreason'] = 'Motivo (obligatorio)';
$string['entryreasonnote'] = 'Nota del motivo';
$string['entrysaved'] = 'Registro guardado.';
$string['entrystatus_added'] = 'Añadida';
$string['entrystatus_done'] = 'Hecho';
$string['entrystatus_not_done'] = 'No hecho';
$string['entrystatus_not_logged'] = 'Sin registrar';
$string['entrystatus_partial'] = 'Parcial';
$string['entrystatus_upcoming'] = 'Futura';
$string['entrystatusfixed'] = 'No hecho (anulación que se conoce de antemano)';
$string['entrywrittenon'] = 'Escrito el {$a}.';
$string['error_baddate'] = 'Active la fecha y elija un día que exista.';
$string['error_changedelsewhere'] = 'Se ha modificado en otro lugar; vuelva a cargar la página para ver la versión más reciente';
$string['error_confirmwarnings'] = 'Confirme que ha leído las advertencias.';
$string['error_dateoutsideyear'] = 'La fecha debe estar dentro del curso escolar.';
$string['error_daterange'] = 'Introduzca una fecha del 2000-01-01 al 2099-12-31.';
$string['error_endbeforestart'] = 'El último día es anterior al primero.';
$string['error_hours'] = 'Introduzca un número de horas de 0 a 24.';
$string['error_name'] = 'Introduzca un nombre de hasta 255 caracteres.';
$string['error_noschoolyear'] = 'Esta programación no tiene curso escolar. Elija antes uno de sus cursos escolares en la configuración de la actividad.';
$string['error_notfound'] = 'Ese elemento no existe o no tiene permiso para verlo.';
$string['error_notimported'] = 'Importe el texto de la programación antes de aprobarla.';
$string['error_planhaserrors'] = 'El texto de la programación tiene errores. Corríjalos; no se ha guardado nada.';
$string['error_planlocked'] = 'Esta programación está aprobada y ya no se puede cambiar.';
$string['error_plannotapproved'] = 'Solo se pueden registrar y exportar sesiones de una programación aprobada.';
$string['error_plantoolarge'] = 'El texto de la programación ocupa más de {$a} KB, el máximo que puede tener una programación.';
$string['error_schoolyear'] = 'Elija uno de sus cursos escolares.';
$string['error_weekday'] = 'El horario tiene un día que no es un día de la semana.';
$string['evententrycreated'] = 'Registro creado';
$string['evententrydeleted'] = 'Registro eliminado';
$string['evententryupdated'] = 'Registro actualizado';
$string['eventplanapproved'] = 'Programación aprobada';
$string['eventplanimported'] = 'Programación importada';
$string['export'] = 'Exportar';
$string['exportcol_block'] = 'Bloque';
$string['exportcol_carry'] = 'Continúa en la sesión siguiente';
$string['exportcol_date'] = 'Fecha';
$string['exportcol_dayslate'] = 'Días de retraso';
$string['exportcol_done'] = 'Realizado';
$string['exportcol_hours'] = 'Horas previstas';
$string['exportcol_loggedby'] = 'Registrado por';
$string['exportcol_measure'] = 'Medida correctora';
$string['exportcol_note'] = 'Nota del motivo';
$string['exportcol_period'] = 'Evaluación';
$string['exportcol_planned'] = 'Previsto';
$string['exportcol_reason'] = 'Motivo';
$string['exportcol_status'] = 'Estado';
$string['exportcol_writtenon'] = 'Escrito el';
$string['exportdownload'] = 'Descargar la hoja de cálculo (XLSX)';
$string['exportfig_added'] = 'Añadida';
$string['exportfig_coverage'] = 'Cobertura (%)';
$string['exportfig_done'] = 'Hecho';
$string['exportfig_group'] = 'Grupo';
$string['exportfig_hoursdone'] = 'Horas impartidas';
$string['exportfig_hoursnotdone'] = 'Horas no impartidas';
$string['exportfig_mostdayslate'] = 'Máximo de días de retraso';
$string['exportfig_notdone'] = 'No hecho';
$string['exportfig_notlogged'] = 'Sin registrar';
$string['exportfig_partial'] = 'Parcial';
$string['exportfig_planned'] = 'Previsto';
$string['exportfig_plannedhours'] = 'Horas previstas';
$string['exportfig_upcoming'] = 'Futura';
$string['exportfig_writtenlate'] = 'Registros con retraso';
$string['exportgroup_all'] = 'Todas las sesiones';
$string['exportgroup_no_block'] = 'Sin bloque';
$string['exportgroup_outside'] = 'Fuera de las evaluaciones';
$string['exportgroup_unplanned'] = 'Sesiones no previstas';
$string['exportintro'] = 'La programación frente a lo registrado, hasta hoy, {$a}. Una sesión todavía sin registrar cuenta como no cubierta, y una parcial, como media; las sesiones futuras quedan fuera de la cobertura. La hoja de cálculo contiene las mismas cifras y una fila por sesión.';
$string['exportlabel_activity'] = 'Actividad';
$string['exportlabel_course'] = 'Curso';
$string['exportlabel_exportedby'] = 'Exportado por';
$string['exportlabel_exportedon'] = 'Exportado el';
$string['exportlabel_module'] = 'Módulo';
$string['exportlabel_plandates'] = 'Fechas de la programación';
$string['exportlabel_plannedhours'] = 'Horas previstas';
$string['exportlabel_plannedsessions'] = 'Sesiones previstas';
$string['exportlabel_schoolyear'] = 'Curso escolar';
$string['exportsheet_plan'] = 'Previsto y realizado';
$string['exportsheet_summary'] = 'Resumen';
$string['exporttable_overall'] = 'Global';
$string['exporttable_perblock'] = 'Por bloque';
$string['exporttable_perperiod'] = 'Por evaluación';
$string['firstclassday'] = 'Primer día de clase';
$string['firstclassday_help'] = 'El primer y el último día de clase de este módulo. El calendario cuenta las sesiones que hay entre ambos en los días de la semana del horario, descontando los festivos del curso escolar. Déjelos sin activar si todavía no los sabe.';
$string['history'] = 'Historial';
$string['historyaction_create'] = 'Creado';
$string['historyaction_delete'] = 'Eliminado';
$string['historyaction_update'] = 'Editado';
$string['historyby'] = 'Por {$a}';
$string['historyentry'] = 'Historial del registro';
$string['historyintro'] = 'Todos los cambios, del más reciente al más antiguo, con el registro tal como quedó después de cada uno (antes, si es una eliminación). El historial no se puede cambiar y se conserva cuando se elimina un registro.';
$string['historyloggedinas'] = 'Por {$a->realuser}, identificado como {$a->user}';
$string['historysession'] = 'Historial de la sesión {$a}';
$string['holiday'] = 'Festivo';
$string['hours'] = 'Horas';
$string['importplan'] = 'Importar el texto de la programación';
$string['importplanintro'] = 'Pegue abajo el texto de la programación o súbalo como archivo Markdown (.md) o de texto, y pulse Vista previa. No se guarda nada hasta que pulse Guardar borrador después de la vista previa.';
$string['issue_bad_date'] = 'El encabezado de día "{$a->text}" no tiene una fecha que se pueda leer. Escriba la fecha como 2026-09-16 o 16/09/2026, opcionalmente precedida de un día de la semana, como lun o dl.';
$string['issue_block_too_long'] = 'El encabezado de RA o bloque que hay encima de este día tiene más de {$a->max} caracteres.';
$string['issue_dates_outside_year'] = 'El primer o el último día de clase de la configuración de la actividad queda fuera del curso escolar ({$a->yearstart} a {$a->yearend}). Cambie los días de clase en la configuración, o el curso escolar, antes de importar o aprobar.';
$string['issue_empty_day'] = 'El día {$a->date} no tiene texto de programación.';
$string['issue_missing_hours'] = 'El día {$a->date} no tiene horas, y el horario no tiene ninguna para ese día de la semana.';
$string['issue_module_too_long'] = 'El encabezado del módulo es demasiado largo: el código puede tener hasta 100 caracteres y el título, hasta 255.';
$string['issue_multiple_modules'] = 'Un segundo encabezado # de módulo. Una programación contiene un solo módulo: elimine este encabezado o importe el módulo por separado.';
$string['issue_no_block'] = 'El día {$a->date} no tiene ningún encabezado ## de RA o bloque encima.';
$string['issue_no_class_weekday'] = 'El día {$a->date} cae en un día de la semana sin clase en el horario.';
$string['issue_no_days'] = 'La programación no tiene días. Escriba cada día de clase como un encabezado ### que empiece por su fecha.';
$string['issue_no_module_heading'] = 'La programación no tiene encabezado # de módulo, así que el código y el título del módulo de esta programación se quedan como están.';
$string['issue_not_utf8'] = 'El texto no está en UTF-8. Guarde el archivo como UTF-8 y vuelva a intentarlo.';
$string['issue_on_holiday'] = 'El día {$a->date} es festivo: {$a->holiday}.';
$string['issue_out_of_order'] = 'El día {$a->date} va después del {$a->previous} en el texto, pero es anterior.';
$string['issue_out_of_range'] = 'La fecha {$a->date} no está entre el 2000-01-01 y el 2099-12-31.';
$string['issue_outside_year'] = 'El día {$a->date} está fuera del curso escolar.';
$string['issue_title_too_long'] = 'El título del día {$a->date} tiene más de {$a->max} caracteres.';
$string['issue_too_many_days'] = 'La programación tiene más de {$a->max} días, el máximo que puede tener una programación. Este es el primer día que supera el límite.';
$string['issue_too_many_hours'] = 'El día {$a->date} tiene {$a->hours} horas; un día puede tener como máximo 24.';
$string['issue_unclosed_fence'] = 'Aquí se abre un bloque de código que no se cierra nunca, así que puede ocultar los días que vienen después.';
$string['issue_unwrapped_fence'] = 'La programación estaba dentro de un bloque de código, así que se ha leído de ese bloque y se ha descartado el texto de alrededor.';
$string['issue_weekday_mismatch'] = 'El día de la semana "{$a->weekday}" no corresponde al {$a->date}.';
$string['issueline'] = 'Línea {$a->line}: {$a->message}';
$string['lastclassday'] = 'Último día de clase';
$string['late'] = '{$a} días de retraso';
$string['lateone'] = '1 día de retraso';
$string['leftover'] = 'Continuación de la sesión anterior';
$string['log'] = 'Registro de sesiones';
$string['logcancellation'] = 'Registrar una anulación';
$string['logged'] = 'Sesiones registradas ({$a})';
$string['logintro'] = 'Registre lo realizado en cada sesión. Una sesión registrada después de su día muestra con cuántos días de retraso se escribió.';
$string['logsession'] = 'Registrar';
$string['logthissession'] = 'Registrar esta sesión';
$string['maxplandays'] = 'Máximo de días por programación';
$string['maxplandays_desc'] = 'Se rechaza el texto de una programación con más días que este valor.';
$string['maxplansize'] = 'Tamaño máximo del texto de la programación (KB)';
$string['maxplansize_desc'] = 'Se rechaza el texto de una programación, pegado o subido, que ocupe más que este valor, en kilobytes.';
$string['module'] = 'Módulo';
$string['modulename'] = 'Seguimiento de clase';
$string['modulename_help'] = 'Una actividad Seguimiento de clase contiene la programación anual de un módulo del curso y el registro de lo realizado en cada sesión, para la inspección. Solo la ven los profesores del curso. Importe la programación en Markdown, revísela y apruébela; después, registre cada sesión frente a lo previsto. Los registros escritos con retraso quedan señalados y todos los cambios se guardan en un historial.';
$string['modulenameplural'] = 'Seguimientos de clase';
$string['month'] = 'Mes';
$string['noblock'] = '(sin RA ni bloque)';
$string['nodeletedentries'] = 'No se ha eliminado ningún registro de esta programación.';
$string['noholidays'] = 'Este curso escolar no tiene festivos.';
$string['noitems'] = 'Esta programación todavía no tiene días. Importe su texto para añadirlos.';
$string['nologged'] = 'Todavía no se ha registrado ninguna sesión.';
$string['nopending'] = 'Nada pendiente: todas las sesiones hasta hoy están registradas.';
$string['noplanactivities'] = 'En este curso no hay ninguna programación de Seguimiento de clase que pueda abrir.';
$string['noschoolyear'] = 'Esta programación en borrador no tiene curso escolar: el curso escolar que usaba se eliminó o la actividad se restauró desde una copia de seguridad. Elija uno de sus cursos escolares en la configuración de la actividad antes de importar o aprobar la programación.';
$string['noschoolyears'] = 'Todavía no tiene ningún curso escolar. Una programación necesita uno para sus festivos y fechas: {$a}.';
$string['noupcoming'] = 'No hay sesiones después de hoy.';
$string['pending'] = 'Sesiones pendientes ({$a})';
$string['pendingintro'] = 'Sesiones hasta hoy que todavía no tienen nada registrado, de la más antigua a la más reciente.';
$string['planapproved'] = 'Programación aprobada.';
$string['plandays'] = 'Programación y calendario';
$string['plandraftsaved'] = 'Borrador guardado. Revise sus días y, después, apruebe la programación.';
$string['planfile'] = 'O suba un archivo';
$string['planfile_help'] = 'Un archivo Markdown (.md) o de texto (.txt), guardado en UTF-8. Se lee en lugar del cuadro de texto y no se guarda.';
$string['planname'] = '{$a->code} · {$a->title}';
$string['plannedcontent'] = 'Contenido previsto';
$string['plannedday'] = 'Previsto para esta sesión';
$string['plansettings'] = 'Programación';
$string['plantext'] = 'Texto de la programación';
$string['pluginadministration'] = 'Administración de Seguimiento de clase';
$string['pluginname'] = 'Seguimiento de clase';
$string['preview'] = 'Vista previa del texto de la programación';
$string['previewclean'] = 'Sin errores ni advertencias.';
$string['previewerrors'] = 'Errores: la programación no se puede guardar hasta que se corrijan.';
$string['previewplan'] = 'Vista previa';
$string['previewreplaces'] = 'El encabezado de módulo del texto sustituye al módulo introducido para esta programación ({$a}).';
$string['previewwarnings'] = 'Advertencias: léalas; se le pedirá que las confirme al aprobar la programación.';
$string['privacy:metadata:classprogress'] = 'Una actividad de seguimiento: la programación anual de un módulo, compartida por los profesores de su curso.';
$string['privacy:metadata:classprogress:approvedby'] = 'El profesor que aprobó la programación.';
$string['privacy:metadata:classprogress:timeapproved'] = 'Cuándo se aprobó y bloqueó la programación.';
$string['privacy:metadata:classprogress_entry'] = 'Lo que pasó realmente en una sesión, escrito por un profesor del curso. El texto puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry:carry'] = 'Si el contenido no terminado pasa a una sesión posterior.';
$string['privacy:metadata:classprogress_entry:content'] = 'Lo realizado en la sesión; puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry:measure'] = 'La medida correctora de una desviación; puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry:reason'] = 'El motivo indicado para una desviación de la programación.';
$string['privacy:metadata:classprogress_entry:reasonnote'] = 'Texto libre sobre el motivo; puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry:sessiondate'] = 'El día en que tuvo lugar la sesión.';
$string['privacy:metadata:classprogress_entry:status'] = 'Si el contenido previsto se hizo, se hizo en parte o no se hizo.';
$string['privacy:metadata:classprogress_entry:timecreated'] = 'Cuándo se creó el registro.';
$string['privacy:metadata:classprogress_entry:timemodified'] = 'Cuándo se modificó el registro por última vez.';
$string['privacy:metadata:classprogress_entry:writtenon'] = 'El día en que se escribió el registro, que muestra si se escribió con retraso.';
$string['privacy:metadata:classprogress_entry_hist'] = 'El historial de todos los cambios de un registro, con quién hizo cada cambio; se conserva después de eliminar el registro. Sus copias del registro contienen texto que puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry_hist:action'] = 'Si el registro se creó, se actualizó o se eliminó.';
$string['privacy:metadata:classprogress_entry_hist:realuserid'] = 'El usuario real, cuando el cambio lo hizo alguien identificado como el usuario.';
$string['privacy:metadata:classprogress_entry_hist:snapshot'] = 'Una copia de todos los campos del registro en el momento del cambio, incluido el texto que puede nombrar a estudiantes.';
$string['privacy:metadata:classprogress_entry_hist:timecreated'] = 'Cuándo se hizo el cambio.';
$string['privacy:metadata:classprogress_entry_hist:userid'] = 'El profesor que hizo el cambio.';
$string['privacy:metadata:classprogress_import'] = 'La lista de importaciones de programaciones. El texto de la programación se guarda en la propia programación.';
$string['privacy:metadata:classprogress_import:contenthash'] = 'Una huella (SHA-1) del texto importado.';
$string['privacy:metadata:classprogress_import:daycount'] = 'El número de días leídos del texto.';
$string['privacy:metadata:classprogress_import:filename'] = 'El nombre del archivo subido.';
$string['privacy:metadata:classprogress_import:source'] = 'Si el texto se pegó o se subió.';
$string['privacy:metadata:classprogress_import:timecreated'] = 'Cuándo se importó la programación.';
$string['privacy:metadata:classprogress_import:userid'] = 'El profesor que importó el texto.';
$string['privacy:path:approval'] = 'Aprobación';
$string['privacy:path:entries'] = 'Registros';
$string['privacy:path:history'] = 'Historial';
$string['privacy:path:imports'] = 'Importaciones';
$string['readwarnings'] = 'He leído las advertencias';
$string['reason_incident'] = 'Incidencia';
$string['reason_other'] = 'Otro';
$string['reason_school_event'] = 'Actividad del centro';
$string['reason_strike'] = 'Huelga';
$string['reason_teacher_absent'] = 'Ausencia del profesor';
$string['reason_trip'] = 'Salida';
$string['resetentries'] = 'Eliminar las sesiones registradas y su historial (las programaciones se conservan)';
$string['saveandnext'] = 'Guardar y registrar la siguiente sesión pendiente (quedan {$a})';
$string['savedraft'] = 'Guardar borrador';
$string['saveentry'] = 'Guardar registro';
$string['schoolyear'] = 'Curso escolar';
$string['schoolyear_help'] = 'Uno de sus cursos escolares, cuyas fechas, festivos y evaluaciones usa la programación. Cuando la programación se aprueba, conserva su propia copia de ellos, así que los cambios posteriores en el curso escolar no la modifican.';
$string['schoolyearkept'] = 'El curso escolar actual (de otro profesor)';
$string['sessiondate'] = 'Fecha de la sesión';
$string['sessions'] = 'Sesiones';
$string['sessionslost'] = 'Sesiones perdidas por festivos';
$string['sessionslostcolumn'] = 'Sesiones perdidas';
$string['sessionspermonth'] = 'Sesiones por mes';
$string['settingslocked'] = 'Esta programación está aprobada: su curso escolar, sus días de clase y su horario ya no se pueden cambiar. Solo el nombre y la descripción se pueden seguir cambiando.';
$string['status'] = 'Estado';
$string['statusapproved'] = 'Aprobada el {$a}. La programación está bloqueada: ya no se pueden cambiar sus días, fechas, horas, texto ni horario.';
$string['statusapprovedshort'] = 'Aprobada';
$string['statusdraft'] = 'Borrador: la programación todavía se puede cambiar, volver a importar o eliminar.';
$string['statusdraftshort'] = 'Borrador';
$string['summaryhours'] = '{$a} horas de clase';
$string['summaryhoursone'] = '{$a} hora de clase';
$string['summarysessions'] = '{$a} sesiones';
$string['summarysessionsone'] = '{$a} sesión';
$string['timetable'] = 'Horario semanal';
$string['timetableintro'] = 'Horas de clase de este módulo cada día de la semana; 0 significa que ese día no hay clase.';
$string['unplannedsession'] = 'Sesión no prevista';
$string['upcoming'] = 'Sesiones futuras ({$a})';
$string['upcomingintro'] = 'Hasta que llega su día, una sesión solo se puede registrar con el estado No hecho, para una anulación que se conoce de antemano.';
$string['weekday'] = 'Día de la semana';
$string['writtenon'] = 'Escrito el';
