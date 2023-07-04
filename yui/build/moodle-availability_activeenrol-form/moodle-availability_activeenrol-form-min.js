YUI.add('moodle-availability_activeenrol-form', function (Y, NAME) {

/**
 * JavaScript for form editing activeenrol conditions.
 *
 * @module moodle-availability_activeenrol-form
 */
// jshint unused:false, undef:false

M.availability_activeenrol = M.availability_activeenrol || {};

/**
 * @class M.availability_activeenrol.form
 * @extends M.core_availability.plugin
 */
M.availability_activeenrol.form = Y.Object(M.core_availability.plugin);

/**
 * activeenrols available for selection (alphabetical order).
 *
 * @property activeenrols
 * @type Array
 */
M.availability_activeenrol.form.enrolmentmethods = null;

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} activeenrols Array of objects containing enrolmentmethodid => name
 */
M.availability_activeenrol.form.initInner = function(enrolmentmethods) {
    this.enrolmentmethods = enrolmentmethods;
};

M.availability_activeenrol.form.getNode = function(json) {
    // Create HTML structure.
    var strings = M.str.availability_activeenrol;

    var html = '<label><span class="pr-3">' + strings.title + ' ' +
            '<span class="availability-activeenrol">' +
            '<select name="id" class="custom-select">' +
            '<option value="choose">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.enrolmentmethods.length; i++) {
        var enrolmentmethod = this.enrolmentmethods[i];
        // String has already been escaped using format_string.
        html += '<option value="' + enrolmentmethod.id + '">' + enrolmentmethod.name + '</option>';
    }
    html += '</select>';
    html += '<select name="valid" class="custom-select">' +
            '<option value="enabled">' + strings.enabled + '</option>' +
            '<option value="valid">' + strings.valid + '</option>' +
            '<option value="any">' + strings.any + '</option>';
    html += '</select>';
    html += '</span></label>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    // Set initial values (leave default 'choose' if creating afresh).
    if (json.creating === undefined) {
        if (json.id !== undefined &&
                node.one('select[name=id] > option[value=' + json.id + ']')) {
            node.one('select[name=id]').set('value', '' + json.id);
        } else if (json.id === undefined) {
            node.one('select[name=id]').set('value', 'any');
        }

        if (json.valid !== undefined &&
                node.one('select[name=valid] > option[value=' + json.valid + ']')) {
            node.one('select[name=valid]').set('value', '' + json.valid);
        } else if (json.valid === undefined) {
            node.one('select[name=valid]').set('value', 'any');
        }
    }

    // Add event handlers (first time only).
    if (!M.availability_activeenrol.form.addedEvents) {
        M.availability_activeenrol.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            // Just update the form fields.
            M.core_availability.form.update();
        }, '.availability_activeenrol select');
    }

    return node;
};

M.availability_activeenrol.form.fillValue = function(value, node) {
    var selected = node.one('select[name=id]').get('value');
    if (selected === 'choose') {
        value.id = 'choose';
    } else if (selected !== 'any') {
        value.id = parseInt(selected, 10);
    }

    var selected = node.one('select[name=valid]').get('value');
    value.valid = selected;
};

M.availability_activeenrol.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);

    // Check enrolmentmethod item id.
    if (value.id && value.id === 'choose') {
        errors.push('availability_activeenrol:error_selectactiveenrol');
    }

    if (value.valid && value.valid === 'choose') {
        errors.push('availability_activeenrol:error_selectstate');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "io", "moodle-core_availability-form"]});
