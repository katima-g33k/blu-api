'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

var _fetch = require('./fetch');

var _fetch2 = _interopRequireDefault(_fetch);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _asyncToGenerator(fn) { return function () { var gen = fn.apply(this, arguments); return new Promise(function (resolve, reject) { function step(key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { return Promise.resolve(value).then(function (value) { step("next", value); }, function (err) { step("throw", err); }); } } return step("next"); }); }; } /* eslint class-methods-use-this: 0 */


var call = void 0;
var auth = {
  user: '',
  pass: '',
  isAdmin: false,
  clear: function clear() {
    this.user = '';
    this.pass = '';
    this.isAdmin = false;
  }
};

var login = function login(username, password) {
  return new Promise(function () {
    var _ref = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee(resolve, reject) {
      var employee;
      return regeneratorRuntime.wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              _context.prev = 0;
              _context.next = 3;
              return call('POST', '/employee/login', { username: username, password: password });

            case 3:
              employee = _context.sent;

              auth.isAdmin = employee.isAdmin;
              auth.user = employee.username;
              auth.pass = password;
              resolve(employee);
              _context.next = 13;
              break;

            case 10:
              _context.prev = 10;
              _context.t0 = _context['catch'](0);

              reject(_context.t0);

            case 13:
            case 'end':
              return _context.stop();
          }
        }
      }, _callee, undefined, [[0, 10]]);
    }));

    return function (_x, _x2) {
      return _ref.apply(this, arguments);
    };
  }());
};

var APIClient = function () {
  function APIClient(apiUrl, apiKey) {
    _classCallCheck(this, APIClient);

    this.apiUrl = apiUrl;
    this.apiKey = apiKey;
    call = _fetch2.default.bind(this, auth);
  }

  _createClass(APIClient, [{
    key: 'category',
    get: function get() {
      return {
        get: function get() {
          return call('GET', '/category');
        }
      };
    }
  }, {
    key: 'employee',
    get: function get() {
      return {
        delete: function _delete(id) {
          return call('DELETE', '/employee/' + id);
        },
        insert: function insert(employee) {
          return call('POST', '/employee', employee);
        },
        list: function list() {
          return call('GET', '/employee');
        },
        login: login,
        logout: function logout() {
          return auth.clear();
        },
        update: function update(id, employee) {
          return call('POST', '/employee/' + id, employee);
        }
      };
    }
  }, {
    key: 'item',
    get: function get() {
      return {
        delete: function _delete(id) {
          return call('DELETE', '/item/' + id);
        },
        exists: function exists(ean13) {
          return call('GET', '/item/exists/' + ean13);
        },
        get: function get(id) {
          return call('GET', '/item/' + id);
        },
        getName: function getName(ean13) {
          return call('GET', '/item/name/' + ean13);
        },
        insert: function insert(item) {
          return call('POST', '/item', item);
        },
        list: function list() {
          return call('GET', '/item');
        },
        merge: function merge(duplicate, id) {
          return call('GET', '/item/' + duplicate + '/merge/' + id);
        },
        search: function search(_search) {
          var outdated = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
          return call('GET', '/item', { search: _search, outdated: outdated });
        },
        status: {
          set: function set(id, status) {
            return call('POST', '/item/' + id + '/status', { status: status });
          }
        },
        storage: {
          clear: function clear() {
            return call('DELETE', 'item/storage');
          },
          set: function set(id, storage) {
            return call('POST', '/item/' + id + '/storage', { storage: storage });
          }
        },
        update: function update(id, item) {
          return call('POST', '/item/' + id, item);
        }
      };
    }
  }, {
    key: 'member',
    get: function get() {
      return {
        comment: {
          delete: function _delete(id) {
            return call('DELETE', '/member/comment/' + id);
          },
          insert: function insert(memberNo, comment, employeeId) {
            return call('POST', '/member/' + memberNo + '/comment', { comment: comment, employee: employeeId });
          },
          update: function update(id, comment, employeeId) {
            return call('POST', '/member/comment/' + id, { comment: comment, employee: employeeId });
          }
        },
        copy: {
          delete: function _delete(id) {
            return call('DELETE', '/member/copy/' + id);
          },
          insert: function insert(memberNo, itemId, price) {
            return call('POST', 'member/' + memberNo + '/copy', { item: itemId, price: price });
          },
          update: function update(id, price) {
            return call('POST', '/member/copy/' + id, { price: price });
          },
          transaction: {
            delete: function _delete(copyId, type) {
              return call('DELETE', 'member/copy/' + copyId + '/transaction', { type: type });
            },
            insert: function insert(memberNo, copyId, type) {
              return call('POST', '/member/' + memberNo + '/copy/' + copyId + '/transaction', { type: type });
            }
          }
        },
        delete: function _delete(no) {
          return call('DELETE', '/member/' + no);
        },
        duplicates: {
          list: function list() {
            return call('GET', '/member/duplicates');
          },
          merge: function merge(duplicate, no) {
            return call('GET', 'member/' + duplicate + '/merger/' + no);
          }
        },
        exists: function exists(_ref2) {
          var email = _ref2.email,
              no = _ref2.no;
          return call('GET', '/member/exists', { email: email, no: no });
        },
        get: function get(no) {
          return call('GET', '/member/' + no);
        },
        getName: function getName(no) {
          return call('GET', '/member/' + no + '/name');
        },
        insert: function insert(member) {
          return call('POST', '/member', member);
        },
        pay: function pay(no) {
          return call('GET', '/member/' + no + '/pay');
        },
        renew: function renew(no) {
          return call('GET', '/member/' + no + '/renew');
        },
        search: function search(_search2) {
          var deactivated = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
          var isParent = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
          return call('GET', '/member', { search: _search2, deactivated: deactivated, isParent: isParent });
        },
        transfer: function transfer(no) {
          return call('GET', '/member/' + no + '/transfer');
        },
        update: function update(no, member) {
          return call('POST', '/member/' + no, member);
        }
      };
    }
  }, {
    key: 'reservation',
    get: function get() {
      return {
        clear: function clear() {
          return call('DELETE', '/reservation');
        },
        delete: function _delete(memberNo, itemId) {
          return call('DELETE', '/reservation', { member: memberNo, item: itemId });
        },
        deleteRange: function deleteRange(from, to) {
          return call('DELETE', '/reservation', { from: from, to: to });
        },
        insert: function insert(memberNo, itemId) {
          return call('POST', '/reservation', { member: memberNo, item: itemId });
        },
        list: function list() {
          return call('GET', '/reservation');
        }
      };
    }
  }, {
    key: 'state',
    get: function get() {
      return {
        list: function list() {
          return call('GET', '/state');
        }
      };
    }
  }, {
    key: 'statistics',
    get: function get() {
      return {
        amountDue: function amountDue(date) {
          return call('GET', '/statistics/amountdue', { date: date });
        },
        byInterval: function byInterval(startDate, endDate) {
          return call('GET', '/statistics/interval', { startDate: startDate, endDate: endDate });
        }
      };
    }
  }]);

  return APIClient;
}();

exports.default = APIClient;