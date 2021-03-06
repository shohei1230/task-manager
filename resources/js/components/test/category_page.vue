<template>
  <div class="body">
    <vue-loading v-if="is_loading" type="spin" color="#333" :size="{ width: '50px', height: '50px' }"></vue-loading>
    <div v-else>
      <div class="container">
        <div class="row mb-4 justify-content-between">
          <div class="col-8">
            <select v-model="selectedPeriod" @change="getItems" class="col">
              <option disabled value=null>期間を選択してください</option>
              <option v-for="period in periods" :value="period.period_id" >{{ periodName(period) }}</option>
            </select>
            <div>
              <p>目標ポイント：{{ periodGoalPoint }}pt</p>
            </div>
          </div>
          <!-- 保存 -->
          <button v-if="getSelectedPeriod()" class="bottom-icon btn col-md-1 col-2" @click="save">
            保存
          </button>
        </div>
        <div class="row">
          <div
            v-for="(item, itemIndex) in items"
            class="col-sm-4 mb-4"
          >
          <div
            :id="'drag-'+item.category_id" 
            class="card dragItem"
            draggable="true"
          >
            <div class="card-header category-header">
              <div class="row">
                <div class="col">
                  <textarea style="width:100%" type="text" :value="item.name" @change="changeCategoryName(itemIndex, $event.target.value)"></textarea>
                </div>
                <button class="btn block p-0 col-1">
                  <span class="far fa-trash-alt ml-2" @click="deleteCategory(itemIndex)"/>
                </button>
              </div>
            </div>
            <div class="card-body pt-0">
              <div class="text-left mb-3">
                <div>{{ countCategoryTasks(item) }}件({{ totalPoint(item) }}pt)</div>
              </div>

              <div 
                v-for="(task, taskIndex) in item.tasks"
                class="task-content mb-3"
              >
                <div class="container dragTask" draggable="true" :id="'task-' + item.category_id + '-' + task.task.task_id">
                  <div class="row">
                    <div class="time-button d-flex align-items-center" >
                      <button v-if="showStartButton(task.task.task_id)" class="btn block fas fa-play-circle fa-2x" @click="startTask(itemIndex, taskIndex, task.task)" :disabled="canStart(task.task.task_id)"/>
                      <button v-else class="btn block fas fa-stop-circle fa-2x" @click="stopTask(itemIndex, taskIndex, task.task)"/>
                    </div>
                    <textarea type="text" class="title-textarea col p-0" :value="task.task.name" @change="changeValue('name', itemIndex, taskIndex, $event.target.value)"></textarea>
                    <div>
                      <div>
                        <button class="btn block">
                          <span class="far fa-trash-alt ml-2" @click="deleteTask(itemIndex, taskIndex)"/>
                        </button>
                      </div>
                      <div>
                        <button class="btn block">
                          <span class="fas fa-pencil-alt ml-2" @click="openModalHour(itemIndex, taskIndex)"/>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 追加ボタン -->
              <button 
                class="btn block"
                @click="addTask(item, itemIndex)"
              >
                <span style="font-size:20px;" class="far fa-plus-square"/>
              </button>
            </div>
          </div>
        </div>
        </div>
        <!-- 追加ボタン -->
        <button 
          class="btn block"
          @click="addCategory()"
        >
          <span style="font-size:20px;" class="far fa-plus-square"/>
        </button>

      </div>

      <modal_hour 
        v-if="is_modal_open"
        :task="task"
        :selected_task="selected_task"
        @confirm="confirm($event)"
        @close="closeModalHour()"
      />
    </div>
  </div>
</template>

<script>
import modal_hour from '../parts/modal_hour';
import drag_drop from '../mixins/drag_drop';
import { mapState } from 'vuex';
import { VueLoading } from 'vue-loading-template'
import moment from "moment";

export default {
  mixins:[drag_drop],
  components:{
    modal_hour,
    VueLoading,
  },
  data(){
    return {
      is_loading: false,
      is_modal_open: false,
      selected_task: {},
      selected_index: {},
      items: {},
      periods: {},
      task: {},
      delete_categories: [],
      delete_tasks: [],
      delete_hours: [],
    };
  },
  created(){
    this.init();
  },
  computed:{
    ...mapState({
      selected_period: (state) => state.period.selected_period,
      is_started: (state) => state.start.is_started
    }),
    selected(){
      return (this.items.length > 0)? true: false;
    },
    selectedPeriod: {
      get () { return this.getSelectedPeriod() },
      set (val) { this.$store.commit('period/setSelectedPeriod', val) },
    },
    periodName(){
      return(period) => {
        const start = new Date(period.start).toLocaleDateString();
        const end = new Date(period.end).toLocaleDateString();
        const date = start + "~" + end;
        return date;
      };
    },
    periodGoalPoint() {
      const period_id= this.getSelectedPeriod();
      if(period_id == null){
        return null;
      }else {
        const selected_period = this.periods.find((period) => period.period_id === period_id);
        return selected_period['goal_point'];
      }
    },
    selectedItems(){
      return this.items;
    },
    totalPoint(){
      return(item)=>{
        let total_point = 0;
        item['tasks'].forEach((task) => {
          const point = Number(task.task.point);
          if(point >= 0){
            total_point += point;
          }
        });

        return total_point;
      };
    },
    nextSort(){
      const sort_length = this.items.length;
      return sort_length + 1;
    },
    showStartButton() {
      return(task_id) => {
        if(this.isStarted.task_id){
          return this.isStarted.task_id !== task_id;
        }

        return true;
      };
    },
    canStart() {
      return(task_id) => {
        return this.isStarted.task_id && task_id !== this.isStarted.task_id;
      };
    },
    isStarted() {
      return this.$store.state.start.is_started;
    },
  },
  methods:{
    init(){
      this.is_loading = true;
      let period_id = null;
      if(this.getSelectedPeriod()){
        period_id = this.getSelectedPeriod();
      }

      axios
        .get('/api/period/' + period_id + '/indexWithItems')
        .then((response) => {
          this.periods = response.data.periods;
          this.items = response.data.items;
          this.is_loading = false;
        });

    },
    save(){
      this.is_loading = true;
      const items = this.items;
      const period_id = this.getSelectedPeriod();
      const delete_tasks = this.delete_tasks;
      const delete_hours = this.delete_hours;
      const delete_categories = this.delete_categories;

      axios
        .post('/api/category/update', {items, period_id, delete_categories, delete_tasks, delete_hours})
        .then((response) => {
          this.is_loading = false;
          this.$toasted.success('更新しました');
          this.items = response.data.items;
          this.delete_tasks = [];
          this.delete_hours = [];
          this.delete_categories = [];
        }).catch((error) =>{
          this.is_loading = false;
          this.$toasted.error('更新出来ませんでした');
      });
    },
    getSelectedPeriod() {
      return this.$store.state.period.selected_period;
    },
    getItems(){
      this.is_loading = true;
      const period_id = this.getSelectedPeriod();

      axios
        .get('/api/category/'+ period_id + '/index')
        .then((response) => {
          this.items = response.data.items;
          this.is_loading = false;
        });
    },
    countCategoryTasks(item){
      return item.tasks.length;
    },
    addTask(item, itemIndex){
      const category_id = item.category_id;
      const new_task = {
        task : {
          'category_id': category_id,
          'name': '',
          'manhours': [],
          'is_new': true,
          'period_id': this.getSelectedPeriod(),
          'task_id': null,
          'point': null,
        }
      };

      this.items[itemIndex]['tasks'].push(new_task);
    },
    addCategory(){
      const new_category = {
        'category_id': null,
        'name': '',
        'sort': this.nextSort,
        'tasks': [],
        'is_new': true,
        'period_id': this.getSelectedPeriod(),
      };

      this.items.push(new_category);
    },
    deleteCategory(itemIndex){
      const item = this.items[itemIndex];
      if(!item.hasOwnProperty('is_new')){
        this.delete_categories.push(item);
      }

      if(itemIndex === this.isStarted.itemIndex){
        this.resetIsStarted();
      }

      this.$delete(this.items, itemIndex);
    },
    deleteTask(itemIndex, taskIndex){
      const item = this.items[itemIndex]['tasks'][taskIndex]['task'];
      if(!item.hasOwnProperty('is_new')){
        this.delete_tasks.push(item);
      }

      if(itemIndex === this.isStarted.itemIndex && taskIndex === this.isStarted.taskIndex){
        this.resetIsStarted();
      }
      this.$delete(this.items[itemIndex]['tasks'], taskIndex);
    },
    changeValue(column, itemIndex, taskIndex, value){
      this.$set(this.items[itemIndex]['tasks'][taskIndex]['task'], column, value);
    },
    changeCategoryName(itemIndex, value){
      this.$set(this.items[itemIndex], 'name', value);
    },
    now(){
      return moment().format();
    },
    startTask(itemIndex, taskIndex, task) {
      const item = {
        'end' :null,
        'title': task.name,
        'remark': null,
        'start': this.now(),
        'task_id': task.task_id,
        'is_new': true,
      };

      axios
        .post('/api/task/start', {item:item})
        .then((response) => {
          this.$toasted.success('開始しました');
          const inserted_item = response.data;
          this.items[itemIndex]['tasks'][taskIndex]['task']['manhours'].push(inserted_item);
          const is_started = {
            itemIndex: itemIndex,
            taskIndex: taskIndex,
            task_id: task.task_id,
            man_hour_id: inserted_item.man_hour_id,
          };
          this.$store.commit('start/setIsStarted', is_started);
        }).catch((error) => {
          this.$toasted.error('開始できませんでした');
        });
    },
    stopTask(itemIndex, taskIndex, task) {
      if(!(itemIndex === this.isStarted.itemIndex & taskIndex === this.isStarted.taskIndex)){
        this.resetIsStarted();
        return;
      }

      const item = this.items[itemIndex]['tasks'][taskIndex]['task']['manhours'].find((manhour) => manhour.man_hour_id === this.isStarted.man_hour_id);
      if(item){
        item.end = this.now();
        axios
        .post('/api/task/stop', {item:item})
        .then((response) => {
          this.$toasted.success('終了しました');
          this.resetIsStarted();
        }).catch((error) => {
          console.log(error);
          this.$toasted.error('終了できませんでした');
        });
      }
    },
    confirm(info){
      const new_items = info.items;
      const delete_hours = info.delete_hours;
      const itemIndex = this.selected_index['item'];
      const taskIndex = this.selected_index['task'];
      this.$set(this.items[itemIndex]['tasks'][taskIndex]['task'], 'manhours', new_items); 
      this.delete_hours = delete_hours;

      const is_deleted = delete_hours.find((hour) => hour.man_hour_id === this.isStarted.man_hour_id);
      if(is_deleted){
        this.resetIsStarted();
      }
    },
    openModalHour(itemIndex, taskIndex){
      this.is_modal_open = true;
      this.selected_index = {'item':itemIndex, 'task': taskIndex};
      this.task = this.items[itemIndex]['tasks'][taskIndex]['task'];
      this.selected_task = this.items[itemIndex]['tasks'][taskIndex]['task']['manhours'];
    },
    closeModalHour(){
      this.is_modal_open = false;
    },
    findCategryIndex(id){
      return this.items.findIndex((item) => item.category_id === id);
    },
    whenDropped(drag_elemet_id, drop_element_id){
      const drag_element = document.querySelector('#' + drag_elemet_id);
      const drop_element = document.querySelector('#' + drop_element_id);

      if(drag_element.className === drop_element.className){
        const drag_id = Number(drag_elemet_id.split('-')[1]);
        const drop_id = Number(drop_element_id.split('-')[1]);
        const drag_item_index = this.findCategryIndex(drag_id);
        const drop_item_index = this.findCategryIndex(drop_id);
        let sort_number = 0;
        // ソート番号変更
        if(this.items[drag_item_index]['sort'] > this.items[drop_item_index]['sort']){
          sort_number = this.items[drop_item_index]['sort'] - 0.5;
        }else if(this.items[drop_item_index]['sort'] > this.items[drag_item_index]['sort']){
          sort_number = this.items[drop_item_index]['sort'] + 0.5
        }
        this.$set(this.items[drag_item_index], 'sort' , sort_number)
  
        // sort順に並び替え
        this.sortItems();
        // 全体のsort番号振り直し
        this.sortNumbering();
      }
      
    },
    sortItems(){
      this.items.sort((a, b) => a.sort - b.sort);
    },
    sortNumbering(){
      for(let i = 0; i < this.items.length; i++){
        this.items[i]['sort'] = i + 1;
      }
    },
    findTask(category_index, task_id){
      return this.items[category_index]['tasks'].findIndex((task) => task.task.task_id === task_id);
    },
    sortTask(category_index){
      this.items[category_index]['tasks'].sort((a, b) => a.task.sort - b.task.sort);
    },
    taskSortNumbering(category_index){
      for(let i = 0; i < this.items[category_index]['tasks'].length; i++){
        this.items[category_index]['tasks'][i]['task']['sort'] = i + 1;
      }
    },
    whenTaskDropped(drag_elemet_id, drop_element_id){
      const drag_element = document.querySelector('#' + drag_elemet_id);
      const drop_element = document.querySelector('#' + drop_element_id);

      if(drag_element.className === drop_element.className){
        const drag_category_id = Number(drag_elemet_id.split('-')[1]);
        const drop_category_id = Number(drop_element_id.split('-')[1]);
        const drag_category_item = this.findCategryIndex(drag_category_id);
        const drop_category_item = this.findCategryIndex(drop_category_id);
        const drag_id = Number(drag_elemet_id.split('-')[2]);
        const drop_id = Number(drop_element_id.split('-')[2]);
        let drag_task = this.findTask(drag_category_item, drag_id);
        const drop_task = this.findTask(drop_category_item, drop_id);

        // 異なるカテゴリーにドロップされた場合、移動元から削除し移動先に追加
        if(drag_category_id !== drop_category_id){
          this.items[drag_category_item]['tasks'][drag_task]['task']['category_id'] = drop_category_id;
          this.items[drop_category_item]['tasks'].push(this.items[drag_category_item]['tasks'][drag_task]);
          this.$delete(this.items[drag_category_item]['tasks'], drag_task);
          drag_task = this.items[drop_category_item]['tasks'].length - 1;
        }

        // sort番号セット
        if(this.items[drop_category_item]['tasks'][drag_task]['task']['sort'] > this.items[drop_category_item]['tasks'][drop_task]['task']['sort']){
          this.items[drop_category_item]['tasks'][drag_task]['task']['sort'] = this.items[drop_category_item]['tasks'][drop_task]['task']['sort'] - 0.5;
        }else if(this.items[drop_category_item]['tasks'][drag_task]['task']['sort'] < this.items[drop_category_item]['tasks'][drop_task]['task']['sort']){
          this.items[drop_category_item]['tasks'][drag_task]['task']['sort'] = this.items[drop_category_item]['tasks'][drop_task]['task']['sort'] + 0.5;
        }

        // タスクのcategory_idからカテゴリーのitemを取得し、その中のtasksの順番を変更
        this.sortTask(drop_category_item);
        // sort番号振り直し　
        this.taskSortNumbering(drop_category_item);
      }
    },
    resetIsStarted(){
      const is_started = {
        itemIndex: null,
        taskIndex: null,
        task_id: null,
        man_hour_id: null
      };

      this.$store.commit('start/setIsStarted', is_started);
    },
  },
}
</script>

<style>

.bottom-icon {
  display: inline-block;
  padding: 0px;
  text-decoration: none;
  background: #66FF99;/*ボタン色*/
  color: #FFF;
  border-bottom: solid 4px #66CC66;
  border-radius: 3px;
}

.bottom-icon:active{
  /*ボタンを押したとき*/
  -webkit-transform: translateY(4px);
  transform: translateY(4px);/*下に動く*/
  box-shadow: 0px 0px 1px rgba(0, 0, 0, 0.2);/*影を小さく*/
  border-bottom: none;
}

.total-task-hour{
  text-align: start;
  cursor: pointer;
}

.title-textarea {
  outline:none;
}

.task-content{
  border-radius: 5%;
}

.time-button {
  margin: 0px;
  padding: 0px;
}
</style>