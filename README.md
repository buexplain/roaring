# roaring

基于[https://github.com/RoaringBitmap/CRoaring](https://github.com/RoaringBitmap/CRoaring)实现的php版本bitmap

## 编译`CRoaring`共享库

```bash
gcc -O2 -g0 -s -fPIC -shared -o src/CRoaring/shared/library.dll src/CRoaring/src/library.c
```
