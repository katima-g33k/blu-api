/* eslint class-methods-use-this: 0 */
import fetch from './fetch';

let call;
const auth = {
  user: '',
  pass: '',
  isAdmin: false,
  clear() {
    this.user = '';
    this.pass = '';
    this.isAdmin = false;
  },
};

const login = (username, password) => new Promise(async (resolve, reject) => {
  try {
    const employee = await call('POST', '/employee/login', { username, password });
    auth.isAdmin = employee.isAdmin;
    auth.user = employee.username;
    auth.pass = password;
    resolve(employee);
  } catch (err) {
    reject(err);
  }
});

export default class APIClient {
  constructor(apiUrl, apiKey) {
    this.apiUrl = apiUrl;
    this.apiKey = apiKey;
    call = fetch.bind(this, auth);
  }

  get category() {
    return {
      get: () => call('GET', '/category'),
    };
  }

  get employee() {
    return {
      delete: id => call('DELETE', `/employee/${id}`),
      insert: employee => call('POST', '/employee', employee),
      list: () => call('GET', '/employee'),
      login,
      logout: () => auth.clear(),
      update: (id, employee) => call('POST', `/employee/${id}`, employee),
    };
  }

  get item() {
    return {
      delete: id => call('DELETE', `/item/${id}`),
      exists: ean13 => call('GET', `/item/exists/${ean13}`),
      get: (id, options) => call('GET', `/item/${id}`, options),
      getName: ean13 => call('GET', `/item/name/${ean13}`),
      insert: item => call('POST', '/item', item),
      list: () => call('GET', '/item'),
      merge: (duplicate, id) => call('GET', `/item/${duplicate}/merge/${id}`),
      search: (search, outdated = false) => call('GET', '/item', { search, outdated }),
      status: {
        set: (id, status) => call('POST', `/item/${id}/status`, { status }),
      },
      storage: {
        clear: () => call('DELETE', '/item/storage'),
        list: () => call('GET', '/item/storage'),
        set: (id, storage) => call('POST', `/item/${id}/storage`, { storage }),
      },
      update: (id, item) => call('POST', `/item/${id}`, item),
    };
  }

  get member() {
    return {
      comment: {
        delete: id => call('DELETE', `/member/comment/${id}`),
        insert: (memberNo, comment, employeeId) => call('POST', `/member/${memberNo}/comment`, { comment, employee: employeeId }),
        update: (id, comment, employeeId) => call('POST', `/member/comment/${id}`, { comment, employee: employeeId }),
      },
      copy: {
        delete: id => call('DELETE', `/member/copy/${id}`),
        insert: (memberNo, itemId, price) => call('POST', `/member/${memberNo}/copy`, { item: itemId, price }),
        update: (id, price) => call('POST', `/member/copy/${id}`, { price }),
        transaction: {
          delete: (copyId, type) => call('DELETE', `/member/copy/${copyId}/transaction`, { type }),
          insert: (memberNo, copyId, type) => call('POST', `/member/${memberNo}/copy/${copyId}/transaction`, { type }),
        },
      },
      delete: no => call('DELETE', `/member/${no}`),
      duplicates: {
        list: () => call('GET', '/member/duplicates'),
        merge: (duplicate, no) => call('GET', `/member/${duplicate}/merge/${no}`),
      },
      exists: ({ email, no }) => call('GET', '/member/exists', { email, no }),
      get: no => call('GET', `/member/${no}`),
      getName: no => call('GET', `/member/${no}/name`),
      insert: member => call('POST', '/member', member),
      pay: no => call('GET', `/member/${no}/pay`),
      renew: no => call('GET', `/member/${no}/renew`),
      search: (search, deactivated = false, isParent = false) => call('GET', '/member', { search, deactivated, isParent }),
      transfer: no => call('GET', `/member/${no}/transfer`),
      update: (no, member) => call('POST', `/member/${no}`, member),
    };
  }

  get reservation() {
    return {
      clear: () => call('DELETE', '/reservation'),
      delete: (memberNo, itemId) => call('DELETE', '/reservation', { member: memberNo, item: itemId }),
      deleteRange: (from, to) => call('DELETE', '/reservation', { from, to }),
      insert: (memberNo, itemId) => call('POST', '/reservation', { member: memberNo, item: itemId }),
      list: () => call('GET', '/reservation'),
    };
  }

  get state() {
    return {
      list: () => call('GET', '/state'),
    };
  }

  get statistics() {
    return {
      amountDue: date => call('GET', '/statistics/amountdue', { date }),
      byInterval: (startDate, endDate) => call('GET', '/statistics/interval', { startDate, endDate }),
    };
  }
}
