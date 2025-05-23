/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import {SeverityEnum} from "@typo3/backend/enum/severity.js";
import "bootstrap";
import $ from "jquery";
import {default as Modal} from "@typo3/backend/modal.js";
import SecurityUtility from "@typo3/core/security-utility.js";
import Icons from "@typo3/backend/icons.js";
import {selector} from "@typo3/core/literals.js";

export class GridEditor {
    constructor(t = null) {
        this.colCount = 1,
        this.rowCount = 1,
        this.readOnly = !1,
        this.nameLabel = "name",
        this.columnLabel = "column label",
        this.defaultCell = {
            spanned: 0,
            rowspan: 1,
            colspan: 1,
            name: "",
            colpos: "",
            column: void 0,
            allowed: {CType: "", list_type: "", tx_gridelements_backend_layout: ""},
            disallowed: {CType: "", list_type: "", tx_gridelements_backend_layout: ""},
            maxitems: 0
        },
        this.selectorEditor = ".t3js-grideditor",
        this.selectorAddColumn = ".t3js-grideditor-addcolumn",
        this.selectorRemoveColumn = ".t3js-grideditor-removecolumn",
        this.selectorAddRowTop = ".t3js-grideditor-addrow-top",
        this.selectorRemoveRowTop = ".t3js-grideditor-removerow-top",
        this.selectorAddRowBottom = ".t3js-grideditor-addrow-bottom",
        this.selectorRemoveRowBottom = ".t3js-grideditor-removerow-bottom",
        this.selectorLinkEditor = ".t3js-grideditor-link-editor",
        this.selectorLinkExpandRight = ".t3js-grideditor-link-expand-right",
        this.selectorLinkShrinkLeft = ".t3js-grideditor-link-shrink-left",
        this.selectorLinkExpandDown = ".t3js-grideditor-link-expand-down",
        this.selectorLinkShrinkUp = ".t3js-grideditor-link-shrink-up",
        this.selectorConfigPreview = ".t3js-grideditor-preview-config",
        this.selectorPreviewArea = ".t3js-tsconfig-preview-area",
        this.selectorCodeMirror = "typo3-t3editor-codemirror",
        this.aCT = Gridelements.BackendLayout.availableCTypes,
        this.aLT = Gridelements.BackendLayout.availableListTypes,
        this.aGT = Gridelements.BackendLayout.availableGridTypes,
        this.modalButtonClickHandler = t => {
            const e = t.target, o = t.currentTarget;
            "cancel" === e.name ? o.hideModal() : "ok" === e.name && (
                this.setName(o.querySelector(".t3js-grideditor-field-name").value, o.userData.col, o.userData.row),
                this.setColumn(parseInt(o.querySelector(".t3js-grideditor-field-colpos").value, 10), o.userData.col, o.userData.row),
                this.setMaxItems(parseInt(o.querySelector(".t3js-grideditor-field-maxitems").value, 10), o.userData.col, o.userData.row),
                typeof this.aCT !== 'undefined' &&
                this.setAllowed($(o).find(".t3js-grideditor-field-allowed").val().join(), o.userData.col, o.userData.row) &&
                this.setDisallowed($(o).find(".t3js-grideditor-field-disallowed").val().join(), o.userData.col, o.userData.row),
                typeof this.aLT !== 'undefined' &&
                this.setAllowedListTypes($(o).find(".t3js-grideditor-field-allowed-list-types").val().join(), o.userData.col, o.userData.row) &&
                this.setDisallowedListTypes($(o).find(".t3js-grideditor-field-disallowed-list-types").val().join(), o.userData.col, o.userData.row),
                typeof this.aGT !== 'undefined' &&
                this.setAllowedGridTypes($(o).find(".t3js-grideditor-field-allowed-grid-types").val().join(), o.userData.col, o.userData.row) &&
                this.setDisallowedGridTypes($(o).find(".t3js-grideditor-field-disallowed-grid-types").val().join(), o.userData.col, o.userData.row),
                this.drawTable(),
                this.writeConfig(this.export2LayoutRecord()), o.hideModal()
            )
        }, this.addColumnHandler = t => {
            t.preventDefault(), this.addColumn(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.removeColumnHandler = t => {
            t.preventDefault(), this.removeColumn(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.addRowTopHandler = t => {
            t.preventDefault(), this.addRowTop(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.addRowBottomHandler = t => {
            t.preventDefault(), this.addRowBottom(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.removeRowTopHandler = t => {
            t.preventDefault(), this.removeRowTop(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.removeRowBottomHandler = t => {
            t.preventDefault(), this.removeRowBottom(), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.linkEditorHandler = t => {
            t.preventDefault();
            const e = $(t.currentTarget);
            this.showOptions(e.data("col"), e.data("row"))
        }, this.linkExpandRightHandler = t => {
            t.preventDefault();
            const e = $(t.currentTarget);
            this.addColspan(e.data("col"), e.data("row")), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.linkShrinkLeftHandler = t => {
            t.preventDefault();
            const e = $(t.currentTarget);
            this.removeColspan(e.data("col"), e.data("row")), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.linkExpandDownHandler = t => {
            t.preventDefault();
            const e = $(t.currentTarget);
            this.addRowspan(e.data("col"), e.data("row")), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        }, this.linkShrinkUpHandler = t => {
            t.preventDefault();
            const e = $(t.currentTarget);
            this.removeRowspan(e.data("col"), e.data("row")), this.drawTable(), this.writeConfig(this.export2LayoutRecord())
        };
        const e = $(this.selectorEditor);
        this.colCount = e.data("colcount"),
        this.rowCount = e.data("rowcount"),
        this.readOnly = e.data("readonly"),
        this.field = $(selector`input[name="${e.data("field")}"]`),
        this.data = e.data("data"),
        this.nameLabel = null !== t ? t.nameLabel : "Name",
        this.columnLabel = null !== t ? t.columnLabel : "Column",
        this.targetElement = $(this.selectorEditor),
        this.initializeEvents(),
        this.addVisibilityObserver(e.get(0)),
        this.drawTable(),
        this.writeConfig(this.export2LayoutRecord())
    }

    static stripMarkup(t) {
        return (new SecurityUtility).stripHtml(t)
    }

    initializeEvents() {
        this.readOnly || ($(document).on("click", this.selectorAddColumn, this.addColumnHandler), $(document).on("click", this.selectorRemoveColumn, this.removeColumnHandler), $(document).on("click", this.selectorAddRowTop, this.addRowTopHandler), $(document).on("click", this.selectorAddRowBottom, this.addRowBottomHandler), $(document).on("click", this.selectorRemoveRowTop, this.removeRowTopHandler), $(document).on("click", this.selectorRemoveRowBottom, this.removeRowBottomHandler), $(document).on("click", this.selectorLinkEditor, this.linkEditorHandler), $(document).on("click", this.selectorLinkExpandRight, this.linkExpandRightHandler), $(document).on("click", this.selectorLinkShrinkLeft, this.linkShrinkLeftHandler), $(document).on("click", this.selectorLinkExpandDown, this.linkExpandDownHandler), $(document).on("click", this.selectorLinkShrinkUp, this.linkShrinkUpHandler))
    }

    getNewCell() {
        return $.extend({}, this.defaultCell)
    }

    writeConfig(t) {
        this.field.val(t);
        const e = t.split("\n");
        let o = "";
        for (const t of e) t && (o += "\t\t\t" + t + "\n");
        const n = "mod.web_layout.BackendLayouts {\n  exampleKey {\n    title = Example\n    icon = content-container-columns-2\n    config {\n" + o.replace(new RegExp("\\t", "g"), "  ") + "    }\n  }\n}\n";
        $(this.selectorConfigPreview).find(this.selectorPreviewArea).empty().append(n);
        const i = document.querySelector(this.selectorCodeMirror);
        i && i.setContent(n);
    }

    addRowTop() {
        const t = [];
        for (let e = 0; e < this.colCount; e++) {
            const o = this.getNewCell();
            o.name = e + "x" + this.data.length, t[e] = o
        }
        this.data.unshift(t), this.rowCount++
    }

    addRowBottom() {
        const t = [];
        for (let e = 0; e < this.colCount; e++) {
            const o = this.getNewCell();
            o.name = e + "x" + this.data.length, t[e] = o
        }
        this.data.push(t), this.rowCount++
    }

    removeRowTop() {
        if (this.rowCount <= 1) return !1;
        const t = [];
        for (let e = 1; e < this.rowCount; e++) t.push(this.data[e]);
        for (let t = 0; t < this.colCount; t++) 1 === this.data[0][t].spanned && this.findUpperCellWidthRowspanAndDecreaseByOne(t, 0);
        return this.data = t, this.rowCount--, !0
    }

    removeRowBottom() {
        if (this.rowCount <= 1) return !1;
        const t = [];
        for (let e = 0; e < this.rowCount - 1; e++) t.push(this.data[e]);
        for (let t = 0; t < this.colCount; t++) 1 === this.data[this.rowCount - 1][t].spanned && this.findUpperCellWidthRowspanAndDecreaseByOne(t, this.rowCount - 1);
        return this.data = t, this.rowCount--, !0
    }

    findUpperCellWidthRowspanAndDecreaseByOne(t, e) {
        const o = this.getCell(t, e - 1);
        return !!o && (1 === o.spanned ? this.findUpperCellWidthRowspanAndDecreaseByOne(t, e - 1) : o.rowspan > 1 && this.removeRowspan(t, e - 1), !0)
    }

    removeColumn() {
        if (this.colCount <= 1) return !1;
        const t = [];
        for (let e = 0; e < this.rowCount; e++) {
            const o = [];
            for (let t = 0; t < this.colCount - 1; t++) o.push(this.data[e][t]);
            1 === this.data[e][this.colCount - 1].spanned && this.findLeftCellWidthColspanAndDecreaseByOne(this.colCount - 1, e), t.push(o)
        }
        return this.data = t, this.colCount--, !0
    }

    findLeftCellWidthColspanAndDecreaseByOne(t, e) {
        const o = this.getCell(t - 1, e);
        return !!o && (1 === o.spanned ? this.findLeftCellWidthColspanAndDecreaseByOne(t - 1, e) : o.colspan > 1 && this.removeColspan(t - 1, e), !0)
    }

    addColumn() {
        for (let t = 0; t < this.rowCount; t++) {
            const e = this.getNewCell();
            e.name = this.colCount + "x" + t, this.data[t].push(e)
        }
        this.colCount++
    }

    drawTable() {
        const t = $('<div class="grideditor-editor-grid">');
        for (let e = 0; e < this.rowCount; e++) {
            if (0 !== this.data[e].length) for (let o = 0; o < this.colCount; o++) {
                const n = this.data[e][o];
                if (1 === n.spanned) continue;
                const i = $('<div class="grideditor-cell">');
                if (i.css("--grideditor-cell-col", o + 1), i.css("--grideditor-cell-colspan", n.colspan), i.css("--grideditor-cell-row", e + 1), i.css("--grideditor-cell-rowspan", n.rowspan), !this.readOnly) {
                    const t = $('<div class="grideditor-cell-actions">');
                    i.append(t);
                    const n = $('<a href="#" data-col="' + o + '" data-row="' + e + '">');
                    Icons.getIcon("actions-open", Icons.sizes.small).then((e => {
                        t.append(n.clone().attr("class", "t3js-grideditor-link-editor grideditor-action grideditor-action-edit").attr("title", TYPO3.lang.grid_editCell).append(e))
                    })), this.cellCanSpanRight(o, e) && Icons.getIcon("actions-caret-right", Icons.sizes.small).then((e => {
                        t.append(n.clone().attr("class", "t3js-grideditor-link-expand-right grideditor-action grideditor-action-expand-right").attr("title", TYPO3.lang.grid_editCell).append(e))
                    })), this.cellCanShrinkLeft(o, e) && Icons.getIcon("actions-caret-left", Icons.sizes.small).then((e => {
                        t.append(n.clone().attr("class", "t3js-grideditor-link-shrink-left grideditor-action grideditor-action-shrink-left").attr("title", TYPO3.lang.grid_editCell).append(e))
                    })), this.cellCanSpanDown(o, e) && Icons.getIcon("actions-caret-down", Icons.sizes.small).then((e => {
                        t.append(n.clone().attr("class", "t3js-grideditor-link-expand-down grideditor-action grideditor-action-expand-down").attr("title", TYPO3.lang.grid_editCell).append(e))
                    })), this.cellCanShrinkUp(o, e) && Icons.getIcon("actions-caret-up", Icons.sizes.small).then((e => {
                        t.append(n.clone().attr("class", "t3js-grideditor-link-shrink-up grideditor-action grideditor-action-shrink-up").attr("title", TYPO3.lang.grid_editCell).append(e))
                    }))
                }
                i.append($('<div class="grideditor-cell-info">').html(
                    "<strong>" + TYPO3.lang.grid_name + ":</strong> "
                    + (n.name ? GridEditor.stripMarkup(n.name) : TYPO3.lang.grid_notSet)
                    + "<br><strong>" + TYPO3.lang.grid_column + ":</strong> "
                    + (void 0 === n.column || isNaN(n.column) ? TYPO3.lang.grid_notSet : parseInt(n.column, 10))
                    + (n.allowed && n.allowed.CType ? "<br><strong>" + TYPO3.lang.grid_allowed + ":</strong> " + n.allowed.CType : "")
                    + (n.allowed && n.allowed.list_type ? "<br><strong>" + TYPO3.lang.grid_allowedListTypes + ":</strong> " + n.allowed.list_type : "")
                    + (n.allowed && n.allowed.tx_gridelements_backend_layout ? "<br><strong>" + TYPO3.lang.grid_allowedGridTypes + ":</strong> " + n.allowed.tx_gridelements_backend_layout : "")
                    + (n.disallowed && n.disallowed.CType ? "<br><strong>" + TYPO3.lang.grid_disallowed + ":</strong> " + n.disallowed.CType : "")
                    + (n.disallowed && n.disallowed.list_type ? "<br><strong>" + TYPO3.lang.grid_disallowedListTypes + ":</strong> " + n.disallowed.list_type : "")
                    + (n.disallowed && n.disallowed.tx_gridelements_backend_layout ? "<br><strong>" + TYPO3.lang.grid_disallowedGridTypes + ":</strong> " + n.disallowed.tx_gridelements_backend_layout : "")
                    + (void 0 === n.maxitems || isNaN(n.maxitems) ? "" : "<br><strong>" + TYPO3.lang.grid_maxitems + ":</strong> " + parseInt(n.maxitems, 10))
                )), t.append(i)
            }
        }
        $(this.targetElement).empty().append(t)
    }

    setName(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.name = GridEditor.stripMarkup(t), !0)
    }

    setColumn(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.column = parseInt(t.toString(), 10), !0)
    }

    setMaxItems(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.maxitems = parseInt(t.toString(), 10), !0)
    }

    setAllowed(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.allowed.CType = GridEditor.stripMarkup(t ? t : '*'))
    }

    setAllowedListTypes(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.allowed.list_type = GridEditor.stripMarkup(t))
    }

    setAllowedGridTypes(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.allowed.tx_gridelements_backend_layout = GridEditor.stripMarkup(t))
    }

    setDisallowed(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.disallowed.CType = GridEditor.stripMarkup(t))
    }

    setDisallowedListTypes(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.disallowed.list_type = GridEditor.stripMarkup(t))
    }

    setDisallowedGridTypes(t, e, o) {
        const n = this.getCell(e, o);
        return !!n && (n.disallowed.tx_gridelements_backend_layout = GridEditor.stripMarkup(t))
    }

    showOptions(t, e) {
        const o = this.getCell(t, e);
        if (!o) return !1;
        let n;
        n = 0 === o.column ? 0 : o.column ? parseInt(o.column.toString(), 10) : "";
        let m;
        m = o.maxitems ? parseInt(o.maxitems.toString(), 10) : 0;
        const i = $("<div>"), r = $('<div class="form-group">'), s = $("<label>"), a = $("<input>"), p = $("<select>");
        i.append([
            r.clone().append([
                s.clone().text(TYPO3.lang.grid_nameHelp),
                a.clone().attr("type", "text").attr("class", "t3js-grideditor-field-name form-control").attr("name", "name").val(GridEditor.stripMarkup(o.name) || "")
            ]),
            r.clone().append([
                s.clone().text(TYPO3.lang.grid_columnHelp),
                a.clone().attr("type", "text").attr("class", "t3js-grideditor-field-colpos form-control").attr("name", "column").val(n)
            ]),
            r.clone().append([
                s.clone().text(TYPO3.lang.grid_maxitemsHelp),
                a.clone().attr("type", "text").attr("class", "t3js-grideditor-field-maxitems form-control").attr("name", "maxitems").val(m)
            ]),
            this.aCT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_allowedHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-allowed form-control").attr("name", "allowed").append(
                    this.getTypeOptions(o.allowed && o.allowed.CType ? o.allowed.CType : "", this.aCT)
                )
            ]),
            this.aCT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_disallowedHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-disallowed form-control").attr("name", "disallowed").append(
                    this.getTypeOptions(o.disallowed && o.disallowed.CType ? o.disallowed.CType : "", this.aCT)
                )
            ]),
            this.aLT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_allowedListTypesHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-allowed-list-types form-control").attr("name", "allowedListTypes").append(
                    this.getTypeOptions(o.allowed && o.allowed.list_type ? o.allowed.list_type: "", this.aLT)
                )
            ]),
            this.aLT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_disallowedListTypesHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-disallowed-list-types form-control").attr("name", "disallowedListTypes").append(
                    this.getTypeOptions(o.disallowed && o.disallowed.list_type ? o.disallowed.list_type: "", this.aLT)
                )
            ]),
            this.aGT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_allowedHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-allowed-grid-types form-control").attr("name", "allowedGridTypes").append(
                    this.getTypeOptions(o.allowed && o.allowed.tx_gridelements_backend_layout ? o.allowed.tx_gridelements_backend_layout : "", this.aGT)
                )
            ]),
            this.aGT && r.clone().append([
                s.clone().text(TYPO3.lang.grid_disallowedHelp),
                p.clone().attr("multiple", "true").attr("class", "t3js-grideditor-field-disallowed-grid-types form-control").attr("name", "disallowedGridTypes").append(
                    this.getTypeOptions(o.disallowed && o.disallowed.tx_gridelements_backend_layout ? o.disallowed.tx_gridelements_backend_layout : "", this.aGT)
                )
            ]),
        ]);
        const l = Modal.show(TYPO3.lang.grid_windowTitle, i, SeverityEnum.notice, [{
            active: !0,
            btnClass: "btn-default",
            name: "cancel",
            text: $(this).data("button-close-text") || TYPO3.lang["button.cancel"] || "Cancel"
        }, {
            btnClass: "btn-primary",
            name: "ok",
            text: $(this).data("button-ok-text") || TYPO3.lang["button.ok"] || "OK"
        }]);
        return l.userData.col = t, l.userData.row = e, l.addEventListener("button.clicked", this.modalButtonClickHandler), !0
    }

    getTypeOptions (stCSV, aT) {
        var aTO = [];

        var sT = [];
        if (stCSV) {
            sT = stCSV.split(',');
        }

        for (var i = 0; i < aT.length; i++) {
            var t = aT[i],
                tK = String(t.key),
                tL = t.label,
                tS = $.inArray(tK, sT) !== -1;

            aTO.push('<option value="' + tK + '" ' + (tS ? ' selected="selected"' : "") + '>' + tL + '</option>');
        }

        return aTO.join("");
    }


    getCell(t, e) {
        return !(t > this.colCount - 1) && (!(e > this.rowCount - 1) && (this.data.length > e - 1 && this.data[e].length > t - 1 ? this.data[e][t] : null))
    }

    cellCanSpanRight(t, e) {
        if (t === this.colCount - 1) return !1;
        const o = this.getCell(t, e);
        if (!o) return !1;
        let n;
        if (o.rowspan > 1) {
            for (let i = e; i < e + o.rowspan; i++) if (n = this.getCell(t + o.colspan, i), !n || 1 === n.spanned || n.colspan > 1 || n.rowspan > 1) return !1
        } else if (n = this.getCell(t + o.colspan, e), !n || 1 === o.spanned || 1 === n.spanned || n.colspan > 1 || n.rowspan > 1) return !1;
        return !0
    }

    cellCanSpanDown(t, e) {
        if (e === this.rowCount - 1) return !1;
        const o = this.getCell(t, e);
        if (!o) return !1;
        let n;
        if (o.colspan > 1) {
            for (let i = t; i < t + o.colspan; i++) if (n = this.getCell(i, e + o.rowspan), !n || 1 === n.spanned || n.colspan > 1 || n.rowspan > 1) return !1
        } else if (n = this.getCell(t, e + o.rowspan), !n || 1 === o.spanned || 1 === n.spanned || n.colspan > 1 || n.rowspan > 1) return !1;
        return !0
    }

    cellCanShrinkLeft(t, e) {
        return this.data[e][t].colspan > 1
    }

    cellCanShrinkUp(t, e) {
        return this.data[e][t].rowspan > 1
    }

    addColspan(t, e) {
        const o = this.getCell(t, e);
        if (!o || !this.cellCanSpanRight(t, e)) return !1;
        for (let n = e; n < e + o.rowspan; n++) this.data[n][t + o.colspan].spanned = 1;
        return o.colspan += 1, !0
    }

    addRowspan(t, e) {
        const o = this.getCell(t, e);
        if (!o || !this.cellCanSpanDown(t, e)) return !1;
        for (let n = t; n < t + o.colspan; n++) this.data[e + o.rowspan][n].spanned = 1;
        return o.rowspan += 1, !0
    }

    removeColspan(t, e) {
        const o = this.getCell(t, e);
        if (!o || !this.cellCanShrinkLeft(t, e)) return !1;
        o.colspan -= 1;
        for (let n = e; n < e + o.rowspan; n++) this.data[n][t + o.colspan].spanned = 0;
        return !0
    }

    removeRowspan(t, e) {
        const o = this.getCell(t, e);
        if (!o || !this.cellCanShrinkUp(t, e)) return !1;
        o.rowspan -= 1;
        for (let n = t; n < t + o.colspan; n++) this.data[e + o.rowspan][n].spanned = 0;
        return !0
    }

    export2LayoutRecord() {
        let t = "backend_layout {\n\tcolCount = " + this.colCount + "\n\trowCount = " + this.rowCount + "\n\trows {\n";
        for (let e = 0; e < this.rowCount; e++) {
            t += "\t\t" + (e + 1) + " {\n", t += "\t\t\tcolumns {\n";
            let o = 0;
            for (let n = 0; n < this.colCount; n++) {
                const i = this.getCell(n, e);
                if (i && !i.spanned) {
                    const r = GridEditor.stripMarkup(i.name) || "";
                    o++, t += "\t\t\t\t" + o + " {\n", t += "\t\t\t\t\tname = " + (r || n + "x" + e) + "\n",
                    i.colspan > 1 && (t += "\t\t\t\t\tcolspan = " + i.colspan + "\n"),
                    i.rowspan > 1 && (t += "\t\t\t\t\trowspan = " + i.rowspan + "\n"),
                    "number" == typeof i.column && (t += "\t\t\t\t\tcolPos = " + i.column + "\n"),
                    i.allowed && (i.allowed.CType || i.allowed.list_type || i.allowed.tx_gridelements_backend_layout) && (t += "\t\t\t\t\tallowed {\n"),
                    i.allowed && i.allowed.CType && (t += "\t\t\t\t\t\tCType = " + i.allowed.CType + "\n"),
                    i.allowed && i.allowed.list_type && (t += "\t\t\t\t\t\tlist_type = " + i.allowed.list_type + "\n"),
                    i.allowed && i.allowed.tx_gridelements_backend_layout && (t += "\t\t\t\t\t\ttx_gridelements_backend_layout = " + i.allowed.tx_gridelements_backend_layout + "\n"),
                    i.allowed && (i.allowed.CType || i.allowed.list_type || i.allowed.tx_gridelements_backend_layout) && (t += "\t\t\t\t\t}\n"),
                    i.disallowed && (i.disallowed.CType || i.disallowed.list_type || i.disallowed.tx_gridelements_backend_layout) && (t += "\t\t\t\t\tdisallowed {\n"),
                    i.disallowed && i.disallowed.CType && (t += "\t\t\t\t\t\tCType = " + i.disallowed.CType + "\n"),
                    i.disallowed && i.disallowed.list_type && (t += "\t\t\t\t\t\tlist_type = " + i.disallowed.list_type + "\n"),
                    i.disallowed &&i.disallowed.tx_gridelements_backend_layout && (t += "\t\t\t\t\t\ttx_gridelements_backend_layout = " + i.disallowed.tx_gridelements_backend_layout + "\n"),
                    i.disallowed && (i.disallowed.CType || i.disallowed.list_type || i.disallowed.tx_gridelements_backend_layout) && (t += "\t\t\t\t\t}\n"),
                    "number" == typeof i.maxitems && i.maxitems > 0 && (t += "\t\t\t\t\tmaxitems = " + i.maxitems + "\n"),
                    t += "\t\t\t\t}\n"
                }
            }
            t += "\t\t\t}\n", t += "\t\t}\n"
        }
        return t += "\t}\n}\n", t
    }

    addVisibilityObserver(t) {
        null === t.offsetParent && new IntersectionObserver((t => {
            t.forEach((t => {
                const e = document.querySelector(this.selectorCodeMirror);
                t.intersectionRatio > 0 && e && e.CodeMirror.refresh()
            }))
        })).observe(t)
    }
}