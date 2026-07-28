/**
 * Hızlı Kasa - Base Print Driver (Driver Strategy Pattern)
 */
(function(HK) {
    'use strict';

    HK.PrintDrivers = HK.PrintDrivers || {};

    class BasePrintDriver {
        constructor(id, name) {
            this.id = id;
            this.name = name;
        }

        async isAvailable() {
            return false;
        }

        async print(options) {
            throw new Error('BasePrintDriver.print() interface method not implemented');
        }
    }

    HK.PrintDrivers.BasePrintDriver = BasePrintDriver;

})(window.HizliKasa = window.HizliKasa || {});
