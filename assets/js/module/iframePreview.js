import '../../css/modules/styleset-preview.scss';
import {observeDom} from '../helper/observeDom';

export default class IframePreview {
    constructor(observableSelector) {
        const iframes = document.querySelectorAll('iframe[data-iframe-body]');
        const self = this;
        this.loadIframes(iframes)
        if (observableSelector === undefined) {
            return;
        }
        observeDom(document.querySelector(observableSelector), function(mutations) {
            self.observeDom(mutations);
        });
    }

    loadBody(iframe) {
        const body = iframe.getAttribute('data-iframe-body');
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.body.innerHTML = body;
        this.adjustHeight(iframe);
    }

    adjustHeight(iframe) {
        if(null === iframe.contentWindow) {
            return;
        }
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

        const height = 30 + iframeDoc.body.offsetHeight;
        iframe.style.height = height + 'px';
    }

    loadIframes(iframes) {
        const self = this;
        [].forEach.call(iframes, function(iframe) {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            if (iframeDoc.readyState  === 'complete') {
                self.loadBody(iframe);
            }
            iframe.addEventListener('load', function () {
                self.loadBody(iframe);
            });
            window.addEventListener('resize',function () {
                self.adjustHeight(iframe);
            });
        });
    }

    observeDom(mutationList) {
        const self = this;
        [].forEach.call(mutationList, function(mutation) {
            if(mutation.addedNodes.length < 1) {
                return;
            }
            const iframes = mutation.target.querySelectorAll('iframe[data-iframe-body]');
            self.loadIframes(iframes)
        });
    }
}