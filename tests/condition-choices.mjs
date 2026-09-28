#!/usr/bin/env node
/**
 * Editor conditions (1.15.0): the value dropdown offers exactly the values
 * a choice field submits, and the "conditional" badge shows only on blocks
 * whose rule is really active.
 *
 * Run:  node tests/condition-choices.mjs
 */
import { choicesFor, CHOICE_OPERATORS, hasActiveCondition } from '../src/shared/condition-choices.js';

let passed = 0;
let failed = 0;
const check = ( label, ok, detail = '' ) => ( ok ? passed++ : ( failed++, console.log( `FAIL: ${ label }${ detail ? ' — ' + detail : '' }` ) ) );
const __ = ( s ) => s;

const sel = choicesFor( 'flinkform/field-select', { options: [ { label: 'Website relaunch', value: 'relaunch' }, { label: 'shop', value: 'shop' }, { label: 'Dup', value: 'shop' }, { label: 'Empty', value: '' } ] }, __ );
check( 'select offers the stored values', JSON.stringify( sel.map( ( c ) => c.value ) ) === '["relaunch","shop"]', JSON.stringify( sel ) );
check( 'label shows label and value', sel[ 0 ].label === 'Website relaunch (relaunch)' );
check( 'label equal to value is not doubled', sel[ 1 ].label === 'shop' );
check( 'radio and checkbox too', choicesFor( 'flinkform/field-radio', { options: [ { label: 'A', value: 'a' } ] }, __ ).length === 1 && choicesFor( 'flinkform/field-checkbox', { options: [] }, __ ).length === 0 );
check( 'toggle compares against 1', JSON.stringify( choicesFor( 'flinkform/field-toggle', {}, __ ) ) === '[{"value":"1","label":"checked"}]' );
check( 'text is free text', choicesFor( 'flinkform/field-text', {}, __ ) === null );
check( 'missing options is an empty list, not a crash', Array.isArray( choicesFor( 'flinkform/field-select', {}, __ ) ) );
check( 'greater_than stays free text', ! CHOICE_OPERATORS.has( 'greater_than' ) && CHOICE_OPERATORS.has( 'is' ) );

const on = { conditionalLogic: { enabled: true, logic: 'all', rules: [ { field: 'a', operator: 'is', value: 'x' } ] } };
check( 'badge for an active rule', hasActiveCondition( 'flinkform/field-text', on ) );
check( 'no badge when switched off', ! hasActiveCondition( 'flinkform/field-text', { conditionalLogic: { ...on.conditionalLogic, enabled: false } } ) );
check( 'no badge without rules', ! hasActiveCondition( 'flinkform/field-text', { conditionalLogic: { enabled: true, rules: [] } } ) );
check( 'no badge on the form (its rule is the submit gate)', ! hasActiveCondition( 'flinkform/form', on ) );
check( 'no badge on foreign blocks', ! hasActiveCondition( 'core/paragraph', on ) );
check( 'page break can carry the badge', hasActiveCondition( 'flinkform/page-break', on ) );

console.log( '' );
if ( failed ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
