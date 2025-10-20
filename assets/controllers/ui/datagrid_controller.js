import { Controller } from '@hotwired/stimulus'
import { getComponent } from '@symfony/ux-live-component';

/* stimulusFetch: 'lazy' */
// noinspection JSUnusedGlobalSymbols
/**
 * @property {boolean} hasMasterTarget
 * @property {HTMLElement} masterTarget
 * @property {HTMLElement[]} masterTargets
 * @property {boolean} hasCheckboxTarget
 * @property {HTMLElement} checkboxTarget
 * @property {HTMLElement[]} checkboxTargets
 * @property {boolean} hasSortableTarget
 * @property {HTMLElement} sortableTarget
 * @property {HTMLElement[]} sortableTargets
 */
export default class extends Controller {
    static targets = ["master", "checkbox", "sortable"]

    async initialize() {
        this.component = await getComponent(this.element)
    }

    masterTargetConnected(element) {
        element.addEventListener("click", this.toggleToggleSelectAllRows.bind(this))
    }

    toggleToggleSelectAllRows() {
        for (let checkbox of this.checkboxTargets) {
            checkbox.checked = this.masterTarget.checked
        }
    }

    sortableTargetConnected(element) {
        element.addEventListener("click", this.columnToggleSort.bind(this))
    }

    columnToggleSort(event) {
        const field = event.target?.dataset.fieldName
        if (field) {
            this.component.action('columnToggleSort', {field: field, append: event.shiftKey})
        }
    }
}
