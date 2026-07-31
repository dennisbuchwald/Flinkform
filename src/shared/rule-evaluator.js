/**
 * Conditional-logic evaluator — the browser half.
 *
 * Deliberately dependency-free and in its own module for two reasons.
 *
 * It has to agree with includes/Conditions/RuleEvaluator.php on every
 * input, including the awkward ones: a field the values map has no entry
 * for, an empty string, an empty array, an unknown operator. Disagreement
 * is not a cosmetic bug — a field hides in the browser and submits anyway,
 * or the reverse. Living here rather than inside view.js means a Node test
 * can run both halves over the same cases (tests/rule-groups-parity.mjs).
 *
 * And it removes a temporal-dead-zone hazard: view.js runs its init during
 * module evaluation, so every constant the evaluator needs had to be
 * declared above that block by hand. Imports are hoisted, so that class of
 * mistake cannot happen here.
 *
 * A rule entry is either a leaf ({field, operator, value}) or a group
 * ({logic, rules}) with its own ALL/ANY. A group's verdict folds into the
 * parent exactly like a leaf's, which is what makes "(A or B or C) and D"
 * expressible — one flat list with a single match mode cannot say it.
 *
 * @package Flinkform
 * @since 1.9.0
 */

const EMPTY_OPERATORS = new Set( [ 'is_empty', 'is_not_empty' ] );

// Ceiling on nesting, mirroring RuleEvaluator::MAX_DEPTH. The editor offers
// one level; this only stops a hand-edited data attribute from recursing
// without bound.
const MAX_RULE_DEPTH = 10;

function evaluateRuleSet( ruleSet, values ) {
	if ( ! ruleSet || ! ruleSet.enabled ) {
		return true;
	}
	return evaluateGroup( ruleSet, values, 0 );
}

/**
 * Evaluate one level of a rule set.
 *
 * Mirrors RuleEvaluator::evaluate_group() on the server, including the
 * awkward edges — the two must agree or a field hides in the browser and
 * submits anyway, or the reverse.
 *
 * An entry is either a leaf ({field, operator, value}) or a nested group
 * ({logic, rules}) with its own ALL/ANY, whose verdict folds into the
 * parent exactly like a leaf's. That is what allows "(A or B or C) and D",
 * which one flat list with a single match mode cannot express.
 *
 * An empty nested group is skipped rather than counted: a group the author
 * has added but not yet filled must not flip the whole condition, which is
 * what a hard true or false would do in ANY and ALL mode respectively.
 *
 * @param {object} group  A rule set or nested group.
 * @param {object} values Current form values keyed by field name.
 * @param {number} depth  Current nesting depth.
 * @returns {boolean}
 */
function evaluateGroup( group, values, depth ) {
	// Guard against a corrupted payload nesting without bound. The editor
	// offers one level; this is only here so a hand-edited data attribute
	// cannot blow the stack.
	if ( depth > MAX_RULE_DEPTH ) {
		return true;
	}

	const rules = Array.isArray( group?.rules ) ? group.rules : [];
	if ( rules.length === 0 ) {
		return true;
	}

	const mode = group.logic === 'any' ? 'any' : 'all';
	let evaluated = 0;

	for ( const entry of rules ) {
		if ( ! entry || typeof entry !== 'object' ) {
			continue;
		}

		let match;
		if ( isRuleGroup( entry ) ) {
			// An empty group says nothing; leave the verdict to its siblings.
			if ( entry.rules.length === 0 ) {
				continue;
			}
			match = evaluateGroup( entry, values, depth + 1 );
		} else {
			match = evaluateRule( entry, values );
		}

		evaluated += 1;

		if ( mode === 'any' && match ) {
			return true;
		}
		if ( mode === 'all' && ! match ) {
			return false;
		}
	}

	// Nothing usable in here — same stance as an empty rule set: a
	// condition that says nothing must not hide anything.
	if ( evaluated === 0 ) {
		return true;
	}

	return mode === 'all';
}

/**
 * A `rules` array is the discriminator between a group and a leaf. Leaves
 * never carry one, so an old flat rule set can never be read as a group.
 *
 * @param {object} entry
 * @returns {boolean}
 */
function isRuleGroup( entry ) {
	return Array.isArray( entry.rules );
}

function evaluateRule( rule, values ) {
	if ( ! rule || typeof rule !== 'object' ) {
		return false;
	}
	const field = String( rule.field ?? '' );
	const operator = String( rule.operator ?? '' );
	const value = String( rule.value ?? '' );

	if ( field === '' || operator === '' ) {
		return false;
	}

	const fieldValue = values[ field ] ?? null;

	if ( EMPTY_OPERATORS.has( operator ) ) {
		const empty = isEmptyValue( fieldValue );
		return operator === 'is_empty' ? empty : ! empty;
	}

	const fieldString = toComparableString( fieldValue );

	switch ( operator ) {
		case 'is':
			// Case-insensitive on purpose — matches the PHP-side
			// RuleEvaluator::evaluate_rule(). See the long comment
			// there for the slugify rationale; in short: "Skip" in
			// the rule UI must match "skip" in the serialised option
			// value the editor's slugify helper produced.
			return fieldString.toLowerCase() === value.toLowerCase();
		case 'is_not':
			return fieldString.toLowerCase() !== value.toLowerCase();
		case 'contains':
			return value !== '' && fieldString.toLowerCase().includes( value.toLowerCase() );
		case 'not_contains':
			return value === '' || ! fieldString.toLowerCase().includes( value.toLowerCase() );
		case 'greater_than':
			if ( fieldString === '' || isNaN( Number( fieldString ) ) || isNaN( Number( value ) ) ) {
				return false;
			}
			return Number( fieldString ) > Number( value );
		case 'less_than':
			if ( fieldString === '' || isNaN( Number( fieldString ) ) || isNaN( Number( value ) ) ) {
				return false;
			}
			return Number( fieldString ) < Number( value );
		case 'date_before':
		case 'date_on_or_after': {
			const dateRe = /^\d{4}-\d{2}-\d{2}$/;
			if ( ! dateRe.test( fieldString ) || ! dateRe.test( value ) ) {
				return false;
			}
			return operator === 'date_before'
				? fieldString < value
				: fieldString >= value;
		}
		default:
			return false;
	}
}

function toComparableString( v ) {
	if ( v === null || v === undefined ) {
		return '';
	}
	if ( Array.isArray( v ) ) {
		return v.map( ( x ) => String( x ) ).join( ', ' );
	}
	if ( typeof v === 'boolean' ) {
		return v ? '1' : '';
	}
	return String( v );
}

function isEmptyValue( v ) {
	if ( v === null || v === undefined ) {
		return true;
	}
	if ( Array.isArray( v ) ) {
		return v.length === 0;
	}
	if ( typeof v === 'string' ) {
		return v.trim() === '';
	}
	if ( typeof v === 'boolean' ) {
		return ! v;
	}
	return false;
}

/**
 * How many passes the visibility resolution may take before it stops.
 *
 * Mirrors VisibilityResolver::MAX_PASSES. Visibility depends on values and
 * values depend on visibility, so it has to be iterated to a fixed point —
 * and a configuration can be genuinely contradictory ("show A when B is
 * empty" against "show B when A is empty"), which oscillates forever. The
 * cap turns that into a defined outcome instead of a hang.
 */
const MAX_VISIBILITY_PASSES = 10;

/**
 * A copy of the values map with every hidden field blanked.
 *
 * Blanked rather than deleted: both evaluators treat a missing key and an
 * empty string identically, and an explicit '' is easier to reason about
 * when reading a debug dump.
 *
 * @param {object}      values Raw values keyed by field name.
 * @param {Set<string>} hidden Field names currently resolved as hidden.
 * @returns {object}
 */
function applyHidden( values, hidden ) {
	if ( hidden.size === 0 ) {
		return values;
	}
	const out = { ...values };
	hidden.forEach( ( name ) => {
		out[ name ] = '';
	} );
	return out;
}

/**
 * Work out which fields are currently hidden, accounting for cascades.
 *
 * A hidden field is excluded from the submission, so its value must not
 * influence any condition either — otherwise the browser and the server
 * disagree about what the form says. The reported case: a postcode select
 * only shown for "Wochenbett" keeps its "andere-plz" value after switching
 * to "Hausgeburt", so a notice stays visible and the submit gate keeps
 * biting, while the server has long since dropped the field.
 *
 * Visibility cannot be resolved in one pass because it depends on values
 * which depend on visibility: hiding A can be what makes B's rule true.
 * So this recomputes the whole set from the raw values each pass, with the
 * previous pass's hidden fields blanked, until the set stops changing.
 *
 * Recomputed from scratch rather than accumulated: a set that only ever
 * grows would terminate faster, but it would also be wrong. If A is hidden
 * in pass one and that makes B's rule true, B has to come back — an
 * accumulating set could never release it.
 *
 * Contradictory configurations oscillate between two states and never
 * settle. Hitting the cap keeps the last computed set: deterministic for a
 * given input and pass order, which is what makes client and server agree
 * even on a form whose rules do not make sense.
 *
 * @param {Array<{names: string[], ruleSet: object}>} entries In document order — the
 *        order matters for the tie-break above, so both sides must use the same.
 * @param {object} values Raw values keyed by field name.
 * @returns {Set<string>} Field names to treat as empty.
 */
function resolveHiddenFields( entries, values ) {
	let hidden = new Set();

	for ( let pass = 0; pass < MAX_VISIBILITY_PASSES; pass++ ) {
		const effective = applyHidden( values, hidden );
		const next = new Set();

		for ( const entry of entries ) {
			if ( ! entry.ruleSet ) {
				continue;
			}
			if ( ! evaluateRuleSet( entry.ruleSet, effective ) ) {
				entry.names.forEach( ( name ) => next.add( name ) );
			}
		}

		if ( next.size === hidden.size && [ ...next ].every( ( n ) => hidden.has( n ) ) ) {
			return hidden;
		}
		hidden = next;
	}

	return hidden;
}

export default evaluateRuleSet;
export {
	evaluateGroup,
	evaluateRule,
	isRuleGroup,
	isEmptyValue,
	toComparableString,
	resolveHiddenFields,
	applyHidden,
	MAX_VISIBILITY_PASSES,
};
