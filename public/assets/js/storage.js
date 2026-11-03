export const storage={
 get(key,fallback=null){try{return JSON.parse(localStorage.getItem('inborgo:'+key))??fallback;}catch{return fallback;}},
 set(key,value){try{localStorage.setItem('inborgo:'+key,JSON.stringify(value));return true;}catch{return false;}},
 remove(key){try{localStorage.removeItem('inborgo:'+key);}catch{}}
};
