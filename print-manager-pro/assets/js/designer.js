/**
 * Print Manager Pro - Online Design Tool
 * Fabric.js-based design editor (Canva-like).
 */
(function($) {
    'use strict';

    var PMP_Designer = {
        canvas: null,
        history: [],
        historyIndex: -1,
        isHistoryAction: false,
        zoomLevel: 1,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            $('#pmp-open-designer').on('click', function() {
                self.openModal();
            });

            $('.pmp-modal-close').on('click', function() {
                self.closeModal();
            });

            // Close on backdrop click
            $('#pmp-designer-modal').on('click', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Tool buttons
            $('.pmp-tool-btn').on('click', function() {
                var tool = $(this).data('tool');
                self.handleTool(tool);
            });

            // Save design
            $('#pmp-designer-save').on('click', function() {
                self.saveDesign();
            });

            // Preview
            $('#pmp-designer-preview').on('click', function() {
                self.previewDesign();
            });

            // Download
            $('#pmp-designer-download').on('click', function() {
                self.downloadDesign();
            });

            // Image file input
            $('#pmp-designer-image-input').on('change', function(e) {
                self.addImage(e.target.files[0]);
                $(this).val('');
            });

            // Color change
            $('#pmp-designer-color').on('change', function() {
                self.changeColor($(this).val());
            });

            // Font size change
            $('#pmp-designer-font-size').on('change', function() {
                self.changeFontSize(parseInt($(this).val()));
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (!self.canvas) return;
                if (e.key === 'Delete' || e.key === 'Backspace') {
                    var active = self.canvas.getActiveObject();
                    if (active && active.type !== 'textbox') {
                        self.canvas.remove(active);
                        self.canvas.renderAll();
                    }
                }
            });
        },

        openModal: function() {
            $('body').addClass('pmp-modal-open');
            $('#pmp-designer-modal').fadeIn(200);
            if (!this.canvas) {
                this.initCanvas();
            }
        },

        closeModal: function() {
            $('#pmp-designer-modal').fadeOut(200);
            $('body').removeClass('pmp-modal-open');
        },

        initCanvas: function() {
            this.canvas = new fabric.Canvas('pmp-designer-canvas', {
                backgroundColor: '#ffffff',
                selection: true,
                preserveObjectStacking: true
            });

            // Add safe zone guides
            this.addSafeZone();

            // Track history for undo/redo
            this.saveHistory();
            var self = this;
            this.canvas.on('object:added object:modified object:removed', function() {
                if ( self.isHistoryAction ) {
                    return;
                }
                self.saveHistory();
            });
        },

        addSafeZone: function() {
            var canvas = this.canvas;
            var margin = 20;

            var border = new fabric.Rect({
                left: margin,
                top: margin,
                width: canvas.width - margin * 2,
                height: canvas.height - margin * 2,
                fill: 'transparent',
                stroke: '#cccccc',
                strokeDashArray: [5, 5],
                strokeWidth: 1,
                selectable: false,
                evented: false,
                excludeFromExport: false
            });

            canvas.add(border);
            canvas.sendToBack(border);
        },

        handleTool: function(tool) {
            switch (tool) {
                case 'text':
                    this.addText();
                    break;
                case 'image':
                    $('#pmp-designer-image-input').click();
                    break;
                case 'line':
                    this.addLine();
                    break;
                case 'rect':
                    this.addRect();
                    break;
                case 'triangle':
                    this.addTriangle();
                    break;
                case 'circle':
                    this.addCircle();
                    break;
                case 'bring_forward':
                    this.bringForward();
                    break;
                case 'send_backward':
                    this.sendBackward();
                    break;
                case 'undo':
                    this.undo();
                    break;
                case 'redo':
                    this.redo();
                    break;
                case 'zoom_in':
                    this.zoomIn();
                    break;
                case 'zoom_out':
                    this.zoomOut();
                    break;
                case 'delete':
                    this.deleteSelected();
                    break;
            }
        },

        addText: function() {
            var color = $('#pmp-designer-color').val();
            var fontSize = parseInt($('#pmp-designer-font-size').val());

            var text = new fabric.Textbox('Votre texte ici', {
                left: 100,
                top: 100,
                fontFamily: 'Arial',
                fontSize: fontSize,
                fill: color,
                width: 300,
                editable: true
            });

            this.canvas.add(text);
            this.canvas.setActiveObject(text);
            this.canvas.renderAll();
        },

        addImage: function(file) {
            if (!file) return;

            var self = this;
            var reader = new FileReader();

            reader.onload = function(e) {
                fabric.Image.fromURL(e.target.result, function(img) {
                    // Scale image to fit canvas
                    var maxWidth = self.canvas.width * 0.8;
                    var maxHeight = self.canvas.height * 0.8;
                    var scale = Math.min(maxWidth / img.width, maxHeight / img.height, 1);

                    img.set({
                        left: (self.canvas.width - img.width * scale) / 2,
                        top: (self.canvas.height - img.height * scale) / 2,
                        scaleX: scale,
                        scaleY: scale
                    });

                    self.canvas.add(img);
                    self.canvas.setActiveObject(img);
                    self.canvas.renderAll();
                });
            };

            reader.readAsDataURL(file);
        },

        addRect: function() {
            var color = $('#pmp-designer-color').val();

            var rect = new fabric.Rect({
                left: 150,
                top: 150,
                width: 120,
                height: 80,
                fill: color,
                opacity: 0.8
            });

            this.canvas.add(rect);
            this.canvas.setActiveObject(rect);
            this.canvas.renderAll();
        },

        addCircle: function() {
            var color = $('#pmp-designer-color').val();

            var circle = new fabric.Circle({
                left: 200,
                top: 150,
                radius: 50,
                fill: color,
                opacity: 0.8
            });

            this.canvas.add(circle);
            this.canvas.setActiveObject(circle);
            this.canvas.renderAll();
        },

        addLine: function() {
            var color = $('#pmp-designer-color').val();

            var line = new fabric.Line([50, 100, 250, 100], {
                left: 100,
                top: 150,
                stroke: color,
                strokeWidth: 4,
                selectable: true
            });

            this.canvas.add(line);
            this.canvas.setActiveObject(line);
            this.canvas.renderAll();
        },

        addTriangle: function() {
            var color = $('#pmp-designer-color').val();

            var triangle = new fabric.Triangle({
                left: 180,
                top: 140,
                width: 120,
                height: 100,
                fill: color,
                opacity: 0.8
            });

            this.canvas.add(triangle);
            this.canvas.setActiveObject(triangle);
            this.canvas.renderAll();
        },

        deleteSelected: function() {
            var active = this.canvas.getActiveObject();
            if (active) {
                this.canvas.remove(active);
                this.canvas.renderAll();
            }
        },

        bringForward: function() {
            var active = this.canvas.getActiveObject();
            if (active) {
                active.bringForward();
                this.canvas.renderAll();
            }
        },

        sendBackward: function() {
            var active = this.canvas.getActiveObject();
            if (active) {
                active.sendBackwards();
                this.canvas.renderAll();
            }
        },

        undo: function() {
            if ( this.historyIndex <= 0 ) {
                return;
            }
            this.historyIndex--;
            this.loadHistory();
        },

        redo: function() {
            if ( this.historyIndex >= this.history.length - 1 ) {
                return;
            }
            this.historyIndex++;
            this.loadHistory();
        },

        saveHistory: function() {
            // Remove any redo history when new action occurs
            this.history = this.history.slice(0, this.historyIndex + 1);
            this.history.push( JSON.stringify( this.canvas.toJSON() ) );
            this.historyIndex = this.history.length - 1;
        },

        loadHistory: function() {
            if ( this.historyIndex < 0 || this.historyIndex >= this.history.length ) {
                return;
            }
            var state = this.history[ this.historyIndex ];
            var self = this;
            this.isHistoryAction = true;
            this.canvas.loadFromJSON( state, this.canvas.renderAll.bind( this.canvas ), function() {
                self.isHistoryAction = false;
            } );
        },

        zoomIn: function() {
            this.zoomLevel = Math.min( this.zoomLevel + 0.1, 3 );
            this.canvas.setZoom( this.zoomLevel );
            this.canvas.renderAll();
        },

        zoomOut: function() {
            this.zoomLevel = Math.max( this.zoomLevel - 0.1, 0.5 );
            this.canvas.setZoom( this.zoomLevel );
            this.canvas.renderAll();
        },

        changeColor: function(color) {
            var active = this.canvas.getActiveObject();
            if (active) {
                active.set('fill', color);
                this.canvas.renderAll();
            }
        },

        changeFontSize: function(size) {
            var active = this.canvas.getActiveObject();
            if (active && active.type === 'textbox') {
                active.set('fontSize', size);
                this.canvas.renderAll();
            }
        },

        saveDesign: function() {
            var designJSON = JSON.stringify(this.canvas.toJSON());
            var productId = $('#pmp-configurator').data('product-id');

            // Store in hidden field for cart submission
            $('#pmp-design-data').val(designJSON);

            // Also save to server
            $.post(pmp_config.ajax_url, {
                action: 'pmp_save_design',
                nonce: pmp_config.nonce,
                design_data: designJSON,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    alert('Design sauvegardé avec succès !');
                } else {
                    alert('Erreur : ' + response.data.message);
                }
            });
        },

        previewDesign: function() {
            var dataURL = this.canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 2
            });

            var win = window.open('', '_blank');
            win.document.write('<html><head><title>Aperçu du design</title></head><body style="margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f0f0f0;">');
            win.document.write('<img src="' + dataURL + '" style="max-width:90%;box-shadow:0 4px 20px rgba(0,0,0,0.2);border-radius:8px;">');
            win.document.write('</body></html>');
            win.document.close();
        },

        downloadDesign: function() {
            var dataURL = this.canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 2
            });

            var link = document.createElement('a');
            link.download = 'design-print-manager-pro.png';
            link.href = dataURL;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    $(document).ready(function() {
        if ($('#pmp-open-designer').length) {
            PMP_Designer.init();
        }
    });

})(jQuery);
