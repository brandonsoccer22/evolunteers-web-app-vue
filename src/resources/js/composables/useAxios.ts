import axios, { AxiosInstance } from 'axios';

export function useAxios(): AxiosInstance {
  const instance = axios.create();
  instance.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  return instance;
}
