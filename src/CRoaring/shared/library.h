#include "stdint.h"
/**
 * Copyright 2025 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
//----------------------------创建、复制、压缩、清空、释放----------------------------
/**
 * Dynamically allocates a new bitmap (initially empty).
 * Returns NULL if the allocation fails.
 * Client is responsible for calling `roaring_bitmap_free()`.
 */
void *bp32_create(void);
/**
 * Dynamically allocates a new bitmap (initially empty).
 * Returns NULL if the allocation fails.
 * Client is responsible for calling `roaring_bitmap_free()`.
 */
void *bp64_create(void);
/**
 * Copies a bitmap (this does memory allocation).
 * The caller is responsible for memory management.
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_copy(void *r);
/**
 * Copies a bitmap (this does memory allocation).
 * The caller is responsible for memory management.
 * The returned pointer may be NULL in case of errors.
 */
void *bp64_copy(void *r);
/** convert array and bitmap containers to run containers when it is more
 * efficient;
 * also convert from run containers when more space efficient.  Returns
 * true if the result has at least one run container.
 */
bool bp32_run_optimize(void *r);
/** convert array and bitmap containers to run containers when it is more
 * efficient;
 * also convert from run containers when more space efficient.  Returns
 * true if the result has at least one run container.
 */
bool bp64_run_optimize(void *r);
/**
 * Empties the bitmap.  It will have no auxiliary allocations (so if the bitmap
 * was initialized in client memory via roaring_bitmap_init(), then a call to
 * roaring_bitmap_clear() would be enough to "free" it)
 */
void bp32_clear(void *r);
/**
 * Empties the bitmap.
 */
void bp64_clear(void *r);
/**
 * Frees the memory.
 */
void bp32_free(void *r);
/**
 * Frees the memory.
 */
void bp64_free(void *r);
//----------------------------添加、删除函数----------------------------
/**
 * Add value x
 */
void bp32_add(void *r, uint32_t x);
/**
 * Adds the provided value to the bitmap.
 */
void bp64_add(void *r, uint64_t x);
/**
 * Add value n_args from pointer vals, faster than repeatedly calling
 * `roaring_bitmap_add()`
 *
 * In order to exploit this optimization, the caller should attempt to keep
 * values with the same "key" (high 16 bits of the value) as consecutive
 * elements in `vals`
 */
void bp32_add_many(void *r, size_t n_args, const uint32_t *vals);
/**
 * Add `n_args` values from `vals`, faster than repeatedly calling
 * `roaring64_bitmap_add()`
 *
 * In order to exploit this optimization, the caller should attempt to keep
 * values with the same high 48 bits of the value as consecutive elements in
 * `vals`.
 */
void bp64_add_many(void *r, size_t n_args, const uint64_t *vals);
/**
 * Add value x
 * Returns true if a new value was added, false if the value already existed.
 */
bool bp32_add_checked(void *r, uint32_t x);
/**
 * Adds the provided value to the bitmap.
 * Returns true if a new value was added, false if the value already existed.
 */
bool bp64_add_checked(void *r, uint64_t x);
/**
 * Add all values in range [min, max)
 */
void bp32_add_range(void *r, uint64_t min, uint64_t max);
/**
 * Add all values in range [min, max).
 */
void bp64_add_range(void *r, uint64_t min, uint64_t max);
/**
 * Remove value x
 */
void bp32_remove(void *r, uint32_t x);
/**
 * Removes a value from the bitmap if present.
 */
void bp64_remove(void *r, uint64_t x);
/**
 * Remove multiple values
 */
void bp32_remove_many(void *r, size_t n_args, uint32_t *vals);
/**
 * Remove multiple values
 */
void bp64_remove_many(void *r, size_t n_args, uint64_t *vals);
/**
 * Remove value x
 * Returns true if a new value was removed, false if the value was not existing.
 */
bool bp32_remove_checked(void *r, uint32_t x);
/**
 * Remove value x
 * Returns true if a new value was removed, false if the value was not existing.
 */
bool bp64_remove_checked(void *r, uint64_t x);
/**
 * Remove all values in range [min, max)
 */
void bp32_remove_range(void *r, uint64_t min, uint64_t max);
/**
 * Remove all values in range [min, max).
 */
void bp64_remove_range(void *r, uint64_t min, uint64_t max);
//----------------------------查询、比较、判断函数----------------------------
/**
 * Get the cardinality of the bitmap (number of elements).
 */
uint64_t bp32_get_cardinality(void *r);
/**
 * Get the cardinality of the bitmap (number of elements).
 */
uint64_t bp64_get_cardinality(void *r);
/**
 * Returns the number of elements in the range [range_start, range_end).
 */
uint64_t bp32_range_cardinality(void *r, uint64_t range_start, uint64_t range_end);
/**
 * Returns the number of elements in the range [min, max).
 */
uint64_t bp64_range_cardinality(void *r, uint64_t range_start, uint64_t range_end);
/**
 * Check if value is present
 */
bool bp32_contains(void *r, uint32_t val);
/**
 * Check if value is present
 */
bool bp64_contains(void *r, uint64_t val);
/**
 * Check whether a range of values from range_start (included) to range_end
 * (excluded) is present
 */
bool bp32_contains_range(void *r, uint64_t range_start, uint64_t range_end);
/**
 * Returns true if all values in the range [range_start, range_end) are present.
 */
bool bp64_contains_range(void *r, uint64_t range_start, uint64_t range_end);
/**
 * roaring_bitmap_rank returns the number of integers that are smaller or equal
 * to x. Thus if x is the first element, this function will return 1. If
 * x is smaller than the smallest element, this function will return 0.
 *
 * The indexing convention differs between roaring_bitmap_select and
 * roaring_bitmap_rank: roaring_bitmap_select refers to the smallest value
 * as having index 0, whereas roaring_bitmap_rank returns 1 when ranking
 * the smallest value.
 */
uint64_t bp32_rank(void *r, uint32_t x);
/**
 * roaring64_bitmap_rank returns the number of integers that are smaller or equal
 * to x. Thus if x is the first element, this function will return 1. If
 * x is smaller than the smallest element, this function will return 0.
 *
 * The indexing convention differs between roaring64_bitmap_select and
 * roaring64_bitmap_rank: roaring64_bitmap_select refers to the smallest value
 * as having index 0, whereas roaring64_bitmap_rank returns 1 when ranking
 * the smallest value.
 */
uint64_t bp64_rank(void *r, uint64_t x);
/**
 * Selects the element at index 'rank' where the smallest element is at index 0.
 * If the size of the roaring bitmap is strictly greater than rank, then this
 * function returns true and sets element to the element of given rank.
 * Otherwise, it returns false.
 */
bool bp32_select(void *r, uint32_t rank, uint32_t *element);
/**
 * Selects the element at index 'rank' where the smallest element is at index 0.
 * If the size of the roaring bitmap is strictly greater than rank, then this
 * function returns true and sets element to the element of given rank.
 * Otherwise, it returns false.
 */
bool bp64_select(void *r, uint64_t rank, uint64_t *element);
/**
 * Returns the smallest value in the set, or UINT32_MAX if the set is empty.
 */
uint32_t bp32_minimum(void *r);
/**
 * Returns the smallest value in the set, or UINT64_MAX if the set is empty.
 */
uint64_t bp64_minimum(void *r);
/**
 * Returns the greatest value in the set, or 0 if the set is empty.
 */
uint32_t bp32_maximum(void *r);
/**
 * Returns the greatest value in the set, or 0 if the set is empty.
 */
uint64_t bp64_maximum(void *r);
/**
 * Return true if the two bitmaps contain the same elements.
 */
bool bp32_equals(void *r1, void *r2);
/**
 * Return true if the two bitmaps contain the same elements.
 */
bool bp64_equals(void *r1, void *r2);
/**
 * Check whether two bitmaps intersect.
 */
bool bp32_intersect(void *r1, void *r2);
/**
 * Check whether two bitmaps intersect.
 */
bool bp64_intersect(void *r1, void *r2);
/**
 * Returns true if the bitmap is empty (cardinality is zero).
 */
bool bp32_is_empty(void *r);
/**
 * Returns true if the bitmap is empty (cardinality is zero).
 */
bool bp64_is_empty(void *r);
//----------------------------并集、交集、差集、称差集----------------------------
/**
 * Computes the union between two bitmaps and returns new bitmap. The caller is
 * responsible for memory management.
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_or(void *r1, void *r2);
/**
 * Computes the union between two bitmaps and returns new bitmap. The caller is
 * responsible for memory management.
 * The returned pointer may be NULL in case of errors.
 */
void *bp64_or(void *r1, void *r2);
/**
 * Inplace version of `roaring_bitmap_or(), modifies r1.
 * TODO: decide whether r1 == r2 ok
 */
void bp32_or_inplace(void *r1, void *r2);
/**
 * Inplace version of `roaring64_bitmap_or(), modifies r1.
 */
void bp64_or_inplace(void *r1, void *r2);
/**
 * Computes the size of the union between two bitmaps.
 */
uint64_t bp32_or_cardinality(void *r1, void *r2);
/**
 * Computes the size of the union between two bitmaps.
 */
uint64_t bp64_or_cardinality(void *r1, void *r2);
/**
 * Computes the symmetric difference (xor) between two bitmaps
 * and returns new bitmap. The caller is responsible for memory management.
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_xor(void *r1, void *r2);
/**
 * Computes the symmetric difference (xor) between two bitmaps and returns a new
 * bitmap. The caller is responsible for free-ing the result.
 * The returned pointer may be NULL in case of errors.
 */
void *bp64_xor(void *r1, void *r2);
/**
 * Inplace version of roaring_bitmap_xor, modifies r1, r1 != r2.
 */
void bp32_xor_inplace(void *r1, void *r2);
/**
 * In-place version of `roaring64_bitmap_xor()`, modifies `r1`. `r1` and `r2`
 * are not allowed to be equal (that would result in an empty bitmap).
 */
void bp64_xor_inplace(void *r1, void *r2);
/**
 * Computes the size of the symmetric difference (xor) between two bitmaps.
 */
uint64_t bp32_xor_cardinality(void *r1, void *r2);
/**
 * Computes the size of the symmetric difference (xor) between two bitmaps.
 */
uint64_t bp64_xor_cardinality(void *r1, void *r2);
/**
 * Computes the intersection between two bitmaps and returns new bitmap. The
 * caller is responsible for memory management.
 *
 * Performance hint: if you are computing the intersection between several
 * bitmaps, two-by-two, it is best to start with the smallest bitmap.
 * You may also rely on roaring_bitmap_and_inplace to avoid creating
 * many temporary bitmaps.
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_and(void *r1, void *r2);
/**
 * Computes the intersection between two bitmaps and returns new bitmap. The
 * caller is responsible for free-ing the result.
 *
 * Performance hint: if you are computing the intersection between several
 * bitmaps, two-by-two, it is best to start with the smallest bitmaps. You may
 * also rely on roaring64_bitmap_and_inplace to avoid creating many temporary
 * bitmaps.
 *
 * The returned pointer may be NULL in case of errors.
 */
void *bp64_and(void *r1, void *r2);
/**
 * Inplace version of `roaring_bitmap_and()`, modifies r1
 * r1 == r2 is allowed.
 *
 * Performance hint: if you are computing the intersection between several
 * bitmaps, two-by-two, it is best to start with the smallest bitmap.
 */
void bp32_and_inplace(void *r1, void *r2);
/**
 * In-place version of `roaring64_bitmap_and()`, modifies `r1`. `r1` and `r2`
 * are allowed to be equal.
 *
 * Performance hint: if you are computing the intersection between several
 * bitmaps, two-by-two, it is best to start with the smallest bitmaps.
 */
void bp64_and_inplace(void *r1, void *r2);
/**
 * Computes the size of the intersection between two bitmaps.
 */
uint64_t bp32_and_cardinality(void *r1, void *r2);
/**
 * Computes the size of the intersection between two bitmaps.
 */
uint64_t bp64_and_cardinality(void *r1, void *r2);
/**
 * Computes the difference (andnot) between two bitmaps and returns new bitmap.
 * Caller is responsible for freeing the result.
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_andnot(void *r1, void *r2);
/**
 * Computes the difference (andnot) between two bitmaps and returns a new
 * bitmap. The caller is responsible for free-ing the result.
 * The returned pointer may be NULL in case of errors.
 */
void *bp64_andnot(void *r1, void *r2);
/**
 * Inplace version of roaring_bitmap_andnot, modifies r1, r1 != r2.
 */
void bp32_andnot_inplace(void *r1, void *r2);
/**
 * In-place version of `roaring64_bitmap_andnot()`, modifies `r1`. `r1` and `r2`
 * are not allowed to be equal (that would result in an empty bitmap).
 */
void bp64_andnot_inplace(void *r1, void *r2);
/**
 * Computes the size of the difference (andnot) between two bitmaps.
 */
uint64_t bp32_andnot_cardinality(void *r1, void *r2);
/**
 * Computes the size of the difference (andnot) between two bitmaps.
 */
uint64_t bp64_andnot_cardinality(void *r1, void *r2);
//----------------------------迭代----------------------------
/**
 * Create an iterator object that can be used to iterate through the values.
 * Caller is responsible for calling `roaring_free_iterator()`.
 *
 * The iterator is initialized (this function calls `roaring_iterator_init()`)
 * If there is a value, then this iterator points to the first value and
 * `it->has_value` is true.  The value is in `it->current_value`.
 */
void *bp32_iterator_create(void *r);
/**
 * Create an iterator object that can be used to iterate through the values.
 * Caller is responsible for calling `roaring64_iterator_free()`.
 *
 * The iterator is initialized. If there is a value, then this iterator points
 * to the first value and `roaring64_iterator_has_value()` returns true. The
 * value can be retrieved with `roaring64_iterator_value()`.
 */
void *bp64_iterator_create(void *r);
/**
 * Reads next ${count} values from iterator into user-supplied ${buf}.
 * Returns the number of read elements.
 * This number can be smaller than ${count}, which means that iterator is
 * drained.
 *
 * This function satisfies semantics of iteration and can be used together with
 * other iterator functions.
 *  - first value is copied from ${it}->current_value
 *  - after function returns, iterator is positioned at the next element
 */
uint32_t bp32_iterator_read(void *r, uint32_t *buf, uint32_t count);
/**
 * Reads up to `count` values from the iterator into the given `buf`. Returns
 * the number of elements read. The number of elements read can be smaller than
 * `count`, which means that there are no more elements in the bitmap.
 *
 * This function can be used together with other iterator functions.
 */
uint64_t bp64_iterator_read(void *r, uint64_t *buf, uint64_t count);
/**
 * Free memory following `roaring_iterator_create()`
 */
void bp32_iterator_free(void *r);
/**
 * Free the iterator.
 */
void bp64_iterator_free(void *r);
//----------------------------序列化、反序列化、转数组----------------------------
/**
 * How many bytes are required to serialize this bitmap.
 *
 * This is meant to be compatible with the Java and Go versions:
 * https://github.com/RoaringBitmap/RoaringFormatSpec
 */
size_t bp32_portable_size_in_bytes(void *r);
/**
 * How many bytes are required to serialize this bitmap.
 *
 * This is meant to be compatible with other languages:
 * https://github.com/RoaringBitmap/RoaringFormatSpec#extension-for-64-bit-implementations
 */
size_t bp64_portable_size_in_bytes(void *r);
/**
 * Write a bitmap to a char buffer.  The output buffer should refer to at least
 * `roaring_bitmap_portable_size_in_bytes(r)` bytes of allocated memory.
 *
 * Returns how many bytes were written which should match
 * `roaring_bitmap_portable_size_in_bytes(r)`.
 *
 * This is meant to be compatible with the Java and Go versions:
 * https://github.com/RoaringBitmap/RoaringFormatSpec
 *
 * This function is endian-sensitive. If you have a big-endian system (e.g., a
 * mainframe IBM s390x), the data format is going to be big-endian and not
 * compatible with little-endian systems.
 *
 * When serializing data to a file, we recommend that you also use
 * checksums so that, at deserialization, you can be confident
 * that you are recovering the correct data.
 */
size_t bp32_portable_serialize(void *r, char *buf);
/**
 * Write a bitmap to a buffer. The output buffer should refer to at least
 * `roaring64_bitmap_portable_size_in_bytes(r)` bytes of allocated memory.
 *
 * Returns how many bytes were written, which should match
 * `roaring64_bitmap_portable_size_in_bytes(r)`.
 *
 * This is meant to be compatible with other languages:
 * https://github.com/RoaringBitmap/RoaringFormatSpec#extension-for-64-bit-implementations
 *
 * This function is endian-sensitive. If you have a big-endian system (e.g., a
 * mainframe IBM s390x), the data format is going to be big-endian and not
 * compatible with little-endian systems.
 *
 * When serializing data to a file, we recommend that you also use
 * checksums so that, at deserialization, you can be confident
 * that you are recovering the correct data.
 */
size_t bp64_portable_serialize(void *r, char *buf);
/**
 * Read bitmap from a serialized buffer safely (reading up to maxbytes).
 * In case of failure, NULL is returned.
 *
 * This is meant to be compatible with the Java and Go versions:
 * https://github.com/RoaringBitmap/RoaringFormatSpec
 *
 * The function itself is safe in the sense that it will not cause buffer
 * overflows: it will not read beyond the scope of the provided buffer
 * (buf,maxbytes).
 *
 * However, for correct operations, it is assumed that the bitmap
 * read was once serialized from a valid bitmap (i.e., it follows the format
 * specification). If you provided an incorrect input (garbage), then the bitmap
 * read may not be in a valid state and following operations may not lead to
 * sensible results. In particular, the serialized array containers need to be
 * in sorted order, and the run containers should be in sorted non-overlapping
 * order. This is is guaranteed to happen when serializing an existing bitmap,
 * but not for random inputs.
 *
 * If the source is untrusted, you should call
 * roaring_bitmap_internal_validate to check the validity of the
 * bitmap prior to using it. Only after calling roaring_bitmap_internal_validate
 * is the bitmap considered safe for use.
 *
 * We also recommend that you use checksums to check that serialized data
 * corresponds to the serialized bitmap. The CRoaring library does not provide
 * checksumming.
 *
 * This function is endian-sensitive. If you have a big-endian system (e.g., a
 * mainframe IBM s390x), the data format is going to be big-endian and not
 * compatible with little-endian systems.
 *
 * The returned pointer may be NULL in case of errors.
 */
void *bp32_portable_deserialize(char *buf, size_t maxbytes);
/**
 * Read a bitmap from a serialized buffer (reading up to maxbytes).
 * In case of failure, NULL is returned.
 *
 * This is meant to be compatible with other languages
 * https://github.com/RoaringBitmap/RoaringFormatSpec#extension-for-64-bit-implementations
 *
 * The function itself is safe in the sense that it will not cause buffer
 * overflows: it will not read beyond the scope of the provided buffer
 * (buf,maxbytes).
 *
 * However, for correct operations, it is assumed that the bitmap
 * read was once serialized from a valid bitmap (i.e., it follows the format
 * specification). If you provided an incorrect input (garbage), then the bitmap
 * read may not be in a valid state and following operations may not lead to
 * sensible results. In particular, the serialized array containers need to be
 * in sorted order, and the run containers should be in sorted non-overlapping
 * order. This is is guaranteed to happen when serializing an existing bitmap,
 * but not for random inputs.
 *
 * If the source is untrusted, you should call
 * roaring64_bitmap_internal_validate to check the validity of the
 * bitmap prior to using it. Only after calling
 * roaring64_bitmap_internal_validate is the bitmap considered safe for use.
 *
 * We also recommend that you use checksums to check that serialized data
 * corresponds to the serialized bitmap. The CRoaring library does not provide
 * checksumming.
 *
 * This function is endian-sensitive. If you have a big-endian system (e.g., a
 * mainframe IBM s390x), the data format is going to be big-endian and not
 * compatible with little-endian systems.
 */
void *bp64_portable_deserialize(char *buf, size_t maxbytes);
/**
 * Convert the bitmap to a sorted array, output in `ans`.
 *
 * Caller is responsible to ensure that there is enough memory allocated, e.g.
 *
 *     ans = malloc(roaring_bitmap_get_cardinality(bitmap) * sizeof(uint32_t));
 */
void bp32_to_uint_array(void *r, uint32_t *ans);
/**
 * Convert the bitmap to a sorted array `out`.
 *
 * Caller is responsible to ensure that there is enough memory allocated, e.g.
 * ```
 * out = malloc(roaring64_bitmap_get_cardinality(bitmap) * sizeof(uint64_t));
 * ```
 */
void bp64_to_uint_array(void *r, uint64_t *ans);